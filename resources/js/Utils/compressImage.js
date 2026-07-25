/**
 * Downscale + re-encode an image in the browser before upload.
 *
 * Field offices register members over mobile data, and a phone camera photo is
 * easily 4–8 MB — slow to upload on a weak signal and heavier than the ID card
 * (or the 2 MB server cap) ever needs. This shrinks the longest edge and
 * re-encodes as JPEG client-side, so what crosses the network is a few hundred KB.
 *
 * Purely an optimization: any failure (unsupported type, decode error, a result
 * that somehow came out larger) falls back to the original file untouched, so
 * the upload still works and the server validation stays authoritative.
 */
export async function compressImage(file, options = {}) {
    const {
        maxDimension = 1600,
        quality = 0.85,
        mimeType = 'image/jpeg',
    } = options;

    if (!(file instanceof File) || !file.type.startsWith('image/')) return file;

    // Already small enough that re-encoding buys little and risks quality loss.
    if (file.size <= 500 * 1024) return file;

    try {
        // `from-image` honours EXIF orientation so portrait phone photos don't
        // come out sideways once the raw pixels are drawn to a canvas.
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });

        const scale = Math.min(1, maxDimension / Math.max(bitmap.width, bitmap.height));
        const width = Math.round(bitmap.width * scale);
        const height = Math.round(bitmap.height * scale);

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext('2d');
        if (!ctx) return file;
        ctx.drawImage(bitmap, 0, 0, width, height);
        bitmap.close?.();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, mimeType, quality));
        if (!blob || blob.size >= file.size) return file;

        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], name, { type: mimeType, lastModified: Date.now() });
    } catch {
        return file;
    }
}
