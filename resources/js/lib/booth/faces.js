// Optional face areas for skin smoothing. Only the browser's own FaceDetector is used (Chrome on Android and
// some desktops); nothing is downloaded and no photo leaves the phone. Without it, smoothing relies on skin colour alone.

let detector;

export const faceDetectionAvailable = () => typeof window !== 'undefined' && 'FaceDetector' in window;

export async function detectFaces(canvas) {
    if (!faceDetectionAvailable()) return [];
    try {
        detector ??= new window.FaceDetector({ fastMode: true, maxDetectedFaces: 4 });
        const found = await detector.detect(canvas);
        return found.map(({ boundingBox: b }) => ({ x: b.x, y: b.y, w: b.width, h: b.height }));
    } catch {
        detector = null;
        return [];
    }
}
