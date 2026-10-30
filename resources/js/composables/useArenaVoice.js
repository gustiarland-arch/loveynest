import { onBeforeUnmount, ref } from 'vue';
import { http } from '@/lib/http';

/**
 * Talking while you play. The two browsers connect directly (WebRTC); the server only passes the small
 * connection notes. Whoever has the lower user id makes the offer, so two people switching on at the same moment
 * never collide. Switching off closes the microphone for real.
 *
 * status: off | asking (waiting for the microphone permission) | waiting (on, partner not on yet) | connecting | on | denied | unsupported
 */
const ICE = { iceServers: [{ urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] }] };

export function useArenaVoice(sessionId, meId, partnerId) {
    const supported = typeof window !== 'undefined' && !!(window.RTCPeerConnection && navigator.mediaDevices?.getUserMedia);
    const status = ref(supported ? 'off' : 'unsupported');
    const offerer = meId < partnerId;
    const base = `/arena/sesi/${sessionId}/suara`;

    let stream = null;
    let pc = null;
    let audio = null;
    let timer = 0;
    let cursor = 0;
    let polling = false;
    let queued = [];
    let remoteSet = false;
    let gone = false;
    let gen = '';
    let offeredAt = 0;
    let beat = 0;

    const send = (type, data, g = gen) => http.post(base, { type, data: JSON.stringify({ g, d: data ?? null }) }).catch(() => {});

    function closePeer() {
        if (pc) {
            pc.onicecandidate = pc.ontrack = pc.onconnectionstatechange = null;
            try {
                pc.close();
            } catch {
                /* already closed */
            }
        }
        pc = null;
        remoteSet = false;
        queued = [];
        if (audio) audio.srcObject = null;
    }

    function makePeer() {
        closePeer();
        pc = new RTCPeerConnection(ICE);
        stream.getTracks().forEach((track) => pc.addTrack(track, stream));
        const mine = gen;
        pc.onicecandidate = (e) => e.candidate && send('ice', e.candidate, mine);
        pc.ontrack = (e) => {
            if (!audio) return;
            audio.srcObject = e.streams[0] ?? new MediaStream([e.track]);
            audio.play().catch(() => {});
        };
        pc.onconnectionstatechange = () => {
            if (!pc || status.value === 'off') return;
            const s = pc.connectionState;
            if (s === 'connected') status.value = 'on';
            else if (s === 'failed' || s === 'disconnected') {
                closePeer();
                status.value = 'waiting'; // the heartbeat below asks the other side to start over
            }
        };
        return pc;
    }

    async function flushIce() {
        remoteSet = true;
        for (const c of queued) await pc?.addIceCandidate(c).catch(() => {});
        queued = [];
    }

    async function startOffer() {
        if (!stream || !offerer) return;
        gen = `${Date.now()}`;
        offeredAt = Date.now();
        const p = makePeer();
        status.value = 'connecting';
        const offer = await p.createOffer();
        await p.setLocalDescription(offer);
        send('offer', p.localDescription);
    }

    async function handle(item) {
        if (status.value === 'off' || !stream) return;
        const note = item.data ? JSON.parse(item.data) : {};
        const data = note.d ?? null;
        if (item.type === 'hello') {
            // two hellos close together mean one start, not two
            if (offerer && Date.now() - offeredAt > 3000) await startOffer();
            else if (!offerer && !pc) send('hello');
        } else if (item.type === 'offer' && !offerer) {
            gen = note.g;
            const p = makePeer();
            status.value = 'connecting';
            await p.setRemoteDescription(data);
            await flushIce();
            const answer = await p.createAnswer();
            await p.setLocalDescription(answer);
            send('answer', p.localDescription);
        } else if (item.type === 'answer' && offerer && pc && !remoteSet && note.g === gen) {
            await pc.setRemoteDescription(data);
            await flushIce();
        } else if (item.type === 'ice' && pc && note.g === gen) {
            if (remoteSet) await pc.addIceCandidate(data).catch(() => {});
            else queued.push(data);
        } else if (item.type === 'bye') {
            closePeer();
            status.value = 'waiting';
        }
    }

    async function poll() {
        if (polling || status.value === 'off') return;
        polling = true;
        try {
            const res = await http.get(`${base}?after=${cursor}`);
            cursor = res.last;
            for (const item of res.items) await handle(item).catch(() => {});
        } catch {
            /* next round */
        } finally {
            polling = false;
        }
    }

    async function on() {
        if (!supported || status.value !== 'off') return;
        status.value = 'asking';
        if (!audio) {
            audio = Object.assign(document.createElement('audio'), { autoplay: true, playsInline: true });
            audio.style.display = 'none';
            document.body.appendChild(audio);
        }
        audio.play().catch(() => {}); // inside the tap, so the phone lets sound play later
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: false });
        } catch {
            status.value = 'denied';
            return;
        }
        if (gone) return release();
        status.value = 'waiting';
        try {
            const res = await http.get(`${base}?after=0`);
            cursor = res.last; // old notes belong to an older call
        } catch {
            /* the poll below catches up */
        }
        await send('hello');
        timer = setInterval(poll, 1000);
        // Not connected yet: the one who answers keeps saying hello, so the offerer always gets a turn.
        beat = setInterval(() => {
            if (!offerer && !pc && status.value === 'waiting') send('hello');
        }, 4000);
    }

    function release() {
        clearInterval(timer);
        clearInterval(beat);
        closePeer();
        stream?.getTracks().forEach((t) => t.stop());
        stream = null;
    }

    function off() {
        if (status.value === 'off' || status.value === 'unsupported') return;
        const wasOn = !!stream;
        release();
        status.value = 'off';
        if (wasOn) send('bye');
    }

    const toggle = () => (status.value === 'off' || status.value === 'denied' ? on() : off());

    onBeforeUnmount(() => {
        gone = true;
        off();
        audio?.remove();
        audio = null;
    });

    return { status, toggle, off, supported };
}
