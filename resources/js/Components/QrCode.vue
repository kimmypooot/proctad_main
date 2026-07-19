<script setup>
import { onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    value: { type: String, required: true },
    /** Displayed size in CSS pixels — the canvas always renders sharp regardless of this. */
    size: { type: Number, default: 160 },
    /** Set false to render a plain QR code with no ProCTAD logo overlay. */
    branded: { type: Boolean, default: true },
});

const canvas = ref(null);
const logo = new Image();
logo.src = '/images/brand/proctad-logo-qr.png';

// Internal bitmap is rendered several times larger than the CSS display size
// so downscaling (always crisp) is used instead of upscaling a low-res bitmap
// (blurry/blocky — easy to mistake for the code being "zoomed in" or cut off).
const RESOLUTION = 4;

const drawLogo = () => {
    if (!canvas.value) return;

    const ctx = canvas.value.getContext('2d');
    const px = props.size * RESOLUTION;
    const logoTarget = px * 0.22;
    const ratio = Math.min(logoTarget / logo.naturalWidth, logoTarget / logo.naturalHeight);
    const dstW = logo.naturalWidth * ratio;
    const dstH = logo.naturalHeight * ratio;
    const padding = logoTarget * 0.12;
    const boxSize = Math.max(dstW, dstH) + padding * 2;
    const boxPos = (px - boxSize) / 2;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(boxPos, boxPos, boxSize, boxSize);
    ctx.drawImage(logo, (px - dstW) / 2, (px - dstH) / 2, dstW, dstH);
};

const draw = () => {
    if (!canvas.value) return;

    const px = props.size * RESOLUTION;

    QRCode.toCanvas(canvas.value, props.value, {
        width: px,
        margin: 3,
        // High correction leaves enough redundancy for the centered logo overlay.
        errorCorrectionLevel: props.branded ? 'H' : 'M',
        color: { dark: '#0f172a', light: '#ffffff' },
    }, (error) => {
        if (error) return;

        // Fixed CSS display size, independent of the parent's box model —
        // the caller sizes its wrapper to match `size`, not the other way around.
        canvas.value.style.width = `${props.size}px`;
        canvas.value.style.height = `${props.size}px`;

        if (!props.branded) return;

        if (logo.complete && logo.naturalWidth > 0) {
            drawLogo();
        } else {
            logo.onload = drawLogo;
        }
    });
};

onMounted(draw);
watch(() => [props.value, props.size, props.branded], draw);

// Exposed so callers can save the rendered code. The canvas holds the
// full-resolution bitmap (size * RESOLUTION), not the downscaled CSS box,
// so the saved PNG is print-quality rather than screen-quality.
defineExpose({
    toDataUrl: () => canvas.value?.toDataURL('image/png') ?? null,
});
</script>

<template>
    <canvas ref="canvas" class="block" :aria-label="`QR code: ${value}`" role="img" />
</template>
