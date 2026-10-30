// Runs the photo effects off the main thread so sliders stay responsive on slower phones.
import { processPixels } from './engine';

self.onmessage = (event) => {
    const { id, buffer, width, height, settings, faces, seed } = event.data;
    try {
        const data = new Uint8ClampedArray(buffer);
        processPixels({ data, width, height }, settings, { faces, seed });
        self.postMessage({ id, buffer: data.buffer }, [data.buffer]);
    } catch (error) {
        self.postMessage({ id, error: String(error?.message ?? error) });
    }
};
