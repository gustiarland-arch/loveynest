import { onBeforeUnmount, ref } from 'vue';

const MAX_SECONDS = 180;
const TYPES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];

/**
 * Records a voice note with the microphone. Nothing is recorded until start()
 * is called from a button press, and the stream is always released afterwards.
 */
export function useVoiceRecorder() {
    const supported = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia && typeof window.MediaRecorder !== 'undefined';
    const recording = ref(false);
    const seconds = ref(0);

    let recorder = null;
    let stream = null;
    let chunks = [];
    let timer = null;
    let startedAt = 0;
    let finish = null;

    function release() {
        clearInterval(timer);
        timer = null;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        recording.value = false;
    }

    /** @throws when the microphone is blocked or missing */
    async function start() {
        if (!supported || recording.value) return;
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const type = TYPES.find((candidate) => window.MediaRecorder.isTypeSupported?.(candidate));
        chunks = [];
        recorder = new window.MediaRecorder(stream, type ? { mimeType: type } : undefined);
        recorder.ondataavailable = (event) => event.data?.size && chunks.push(event.data);
        recorder.onstop = () => {
            const elapsed = Math.max(1, Math.round((Date.now() - startedAt) / 1000));
            const mime = (recorder.mimeType || type || 'audio/mp4').split(';')[0];
            const blob = new Blob(chunks, { type: mime });
            release();
            finish?.({ blob, seconds: Math.min(elapsed, MAX_SECONDS), mime });
            finish = null;
        };
        recorder.start();
        startedAt = Date.now();
        seconds.value = 0;
        recording.value = true;
        timer = setInterval(() => {
            seconds.value = Math.round((Date.now() - startedAt) / 1000);
            if (seconds.value >= MAX_SECONDS) recorder?.state === 'recording' && recorder.stop();
        }, 250);
    }

    /** Stops and resolves with the recording, or null when nothing was captured. */
    function stop() {
        return new Promise((resolve) => {
            if (!recorder || recorder.state !== 'recording') {
                release();
                return resolve(null);
            }
            finish = (result) => resolve(result.blob.size ? result : null);
            recorder.stop();
        });
    }

    function cancel() {
        finish = null;
        if (recorder && recorder.state === 'recording') {
            recorder.onstop = null;
            recorder.stop();
        }
        release();
    }

    onBeforeUnmount(cancel);

    return { supported, recording, seconds, start, stop, cancel };
}
