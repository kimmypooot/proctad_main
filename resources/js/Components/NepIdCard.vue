<script setup>
import AppIcon from './AppIcon.vue';
import FlipCard from './FlipCard.vue';
import QrCode from './QrCode.vue';

defineProps({
    /** Payload from App\Support\NepIdCard::data() */
    card: { type: Object, required: true },
});
</script>

<template>
    <!-- Mirrors legacy assets/css/testadmin/pages/id-card.css exactly: role/ID
         subtitle, photo/QR row, underlined name — over the real F/B-ID-Template art. -->
    <FlipCard class="mx-auto w-full max-w-[280px]">
        <template #front>
            <div
                class="relative aspect-[24/29] w-full overflow-hidden rounded-2xl shadow-md"
                style="background-image: url('/images/id-templates/F-ID-Template.jpg'); background-size: 100% 100%; background-repeat: no-repeat;"
            >
                <p class="absolute left-1/2 top-[21%] -translate-x-1/2 whitespace-nowrap text-[1.1rem] font-bold text-slate-800">
                    {{ card.personnel_type_label }}
                </p>
                <p class="absolute left-1/2 top-[28%] -translate-x-1/2 whitespace-nowrap text-[0.65rem] font-semibold tracking-wide text-slate-500">
                    {{ card.nep_id }}
                </p>

                <!-- Fixed px sizing matches legacy id-card.css exactly (100px photo /
                     130px QR within a 280px-max card) — percentage-based boxes here
                     caused the QR canvas to be upscaled from a lower-res bitmap and
                     look blurry/"zoomed in". -->
                <div class="absolute left-1/2 top-[33%] flex w-[88%] -translate-x-1/2 items-center justify-center gap-1.5">
                    <div class="h-[100px] w-[100px] shrink-0 overflow-hidden rounded-lg border-2 border-slate-200 bg-white">
                        <img
                            v-if="card.photo_url"
                            :src="card.photo_url"
                            :alt="`Photo of ${card.name}`"
                            class="h-full w-full object-cover"
                        >
                        <AppIcon v-else name="user-circle" class="h-full w-full p-2 text-slate-300" />
                    </div>
                    <div class="flex h-[130px] w-[130px] shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-slate-200 bg-white p-1">
                        <!-- Not part of the PROCTAD corps — no ProCTAD program logo on the QR. -->
                        <QrCode :value="card.qr_value" :size="122" :branded="false" />
                    </div>
                </div>

                <p class="absolute bottom-[20%] left-1/2 w-[90%] -translate-x-1/2 overflow-hidden text-center text-base font-bold text-slate-800 underline decoration-2 underline-offset-4">
                    {{ card.name }}
                </p>
            </div>
        </template>

        <template #back>
            <div
                class="aspect-[24/29] w-full overflow-hidden rounded-2xl shadow-md"
                style="background-image: url('/images/id-templates/B-ID-Template.jpg'); background-size: 100% 100%; background-repeat: no-repeat;"
            />
        </template>
    </FlipCard>
</template>
