<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FileInput from '@/Components/FileInput.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import TextInput from '@/Components/TextInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { useFormErrors } from '@/Composables/useFormErrors';

/** Keys whose humanised form would not match the label on the control. */
const errorLabels = { photo: 'ID Photo' };

const props = defineProps({
    member: { type: Object, default: null },
    requirements: { type: Array, default: () => [] },
    requirementsTotal: { type: Number, required: true },
});

const compliedCount = props.requirements.filter((req) => req.complied).length;
const compliancePercent = props.requirementsTotal
    ? Math.round((compliedCount / props.requirementsTotal) * 100)
    : 0;

const detailFields = (member) => [
    // A test administrator is based at a testing center. Only the members who
    // are CSC staff have a field office of their own, so it shows when set
    // rather than standing in for the center.
    ['map-pin', 'Testing Center', member.testing_center ?? '—'],
    ...(member.field_office ? [['building-library', 'Field Office', member.field_office]] : []),
    ['briefcase', 'Agency', member.agency],
    ['identification', 'Position', member.position ?? '—'],
    ['envelope', 'Email', member.email],
    ['phone', 'Mobile', member.mobile_number],
];

// Self-service edit covers identity + contact info; sex, agency, and Testing
// Center stay staff-controlled — those are what the Field Office verified
// when registering the member (see MyProctadController).
/* --- Requirement document submission ---
   Uses a bare router.post rather than useForm: each row uploads independently,
   so one shared form's processing/error state would bleed across all of them. */
const uploadingKey = ref(null);
const uploadErrors = ref({});

const uploadRequirement = (req, event) => {
    const file = event.target.files[0];
    if (!file) return;

    uploadingKey.value = req.key;
    uploadErrors.value = { ...uploadErrors.value, [req.key]: null };

    router.post(`/my/requirements/${req.key}`, { file }, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
            uploadErrors.value = { ...uploadErrors.value, [req.key]: errors.file ?? 'Upload failed.' };
        },
        onFinish: () => {
            uploadingKey.value = null;
            // Let the same file be chosen again after a failure.
            event.target.value = '';
        },
    });
};

const editing = ref(false);
const form = useForm({
    first_name: props.member?.first_name ?? '',
    middle_name: props.member?.middle_name ?? '',
    last_name: props.member?.last_name ?? '',
    suffix: props.member?.suffix ?? '',
    email: props.member?.email ?? '',
    mobile_number: props.member?.mobile_number ?? '',
    position: props.member?.position ?? '',
    photo: null,
});

useFormErrors(form);

const startEditing = () => {
    form.reset();
    form.clearErrors();
    editing.value = true;
};

const submit = () => form
    .transform((data) => ({ ...data, _method: 'put' }))
    .post('/my/profile', {
        forceFormData: true,
        onSuccess: () => (editing.value = false),
    });
</script>

<template>
    <Head title="My Profile" />

    <DashboardLayout>
        <DashboardPageHeader title="My PROCTAD Profile">
            <template v-if="member && !editing" #actions>
                <BaseButton variant="outline" size="sm" @click="startEditing" icon="pencil">
                    Edit Profile
                </BaseButton>
            </template>
        </DashboardPageHeader>

        <div v-if="!member" class="mt-6">
            <EmptyState
                icon="identification"
                title="No PROCTAD record linked to your account"
                description="Your Field Office has not yet registered you in the PROCTAD registry, or your registry record uses a different email address. Please contact your Field Office."
            />
        </div>

        <div v-else class="mt-6 grid gap-6 lg:grid-cols-3">
            <!-- Identity card -->
            <BaseCard padding="none" class="overflow-hidden">
                <div class="bg-gradient-to-br from-brand-700 to-brand-800 px-6 pb-8 pt-6 text-white">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-white/30 bg-white/10">
                            <UserAvatar :src="member.photo_url" :name="member.name">
                                <AppIcon name="user-circle" class="h-10 w-10 text-white/70" />
                            </UserAvatar>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-lg font-bold">{{ member.name }}</p>
                            <p class="font-mono text-xs font-semibold text-brand-200">{{ member.proctad_id }}</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <BaseBadge :variant="member.status_variant">{{ member.status_label }}</BaseBadge>
                    </div>
                </div>

                <dl v-if="!editing" class="space-y-4 p-6 text-sm">
                    <div v-for="[icon, label, value] in detailFields(member)" :key="label" class="flex items-start gap-2.5">
                        <AppIcon :name="icon" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ label }}</dt>
                            <dd class="mt-0.5 truncate text-slate-700">{{ value }}</dd>
                        </div>
                    </div>

                    <!-- Members cannot change the Google account they sign in with, so
                         this is information rather than a prompt: an agency account is
                         disabled when government service ends, and knowing that in
                         advance is what lets someone save their records first. -->
                    <div class="flex items-start gap-2.5 border-t border-slate-100 pt-4">
                        <AppIcon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <p class="text-xs leading-relaxed text-slate-500">
                            You sign in with Google. If that account belongs to your agency, it stops
                            working when you resign or retire — download any certificates and service
                            records you need before then, or ask CSC RO VIII for a copy afterwards.
                        </p>
                    </div>
                </dl>

                <form v-else class="space-y-4 p-6" novalidate @submit.prevent="submit">
                    <FormErrorSummary :errors="form.errors" :labels="errorLabels" />
                    <FileInput
                        v-model="form.photo" name="photo"
                        label="ID Photo"
                        optional
                        compress
                        accept="image/*"
                        :error="form.errors.photo"
                        hint="JPG or PNG, max 2 MB. If you sign in with Google, your Google photo is shown instead."
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <TextInput
                            v-model="form.first_name" name="first_name"
                            label="First Name"
                            required
                            :error="form.errors.first_name"
                        />
                        <TextInput
                            v-model="form.last_name" name="last_name"
                            label="Last Name"
                            required
                            :error="form.errors.last_name"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <TextInput
                            v-model="form.middle_name" name="middle_name"
                            label="Middle Name"
                            optional
                            :error="form.errors.middle_name"
                        />
                        <TextInput
                            v-model="form.suffix" name="suffix"
                            label="Suffix"
                            optional
                            placeholder="Jr., III"
                            :error="form.errors.suffix"
                        />
                    </div>
                    <!-- The previous hint claimed this address was used to sign in with
                         Google and that changing it could affect sign-in. It isn't and it
                         can't: updateProfile writes members.email only, while Google
                         matches on users.email and google_id. The warning discouraged
                         members from correcting the one address the system actually
                         contacts them on. -->
                    <TextInput
                        v-model="form.email" name="email"
                        label="Email Address"
                        type="email"
                        required
                        :error="form.errors.email"
                        hint="Where we send assignment confirmations and certificate notices — keep it an address you can read. This doesn't change how you sign in."
                    />
                    <TextInput
                        v-model="form.mobile_number" name="mobile_number"
                        label="Mobile Number"
                        required
                        inputmode="tel"
                        placeholder="09171234567"
                        :error="form.errors.mobile_number"
                    />
                    <TextInput
                        v-model="form.position" name="position"
                        label="Position"
                        optional
                        :error="form.errors.position"
                    />
                    <p class="text-xs text-slate-400">
                        Sex, agency, and Testing Center are managed by your Field Office — contact them to update these.
                    </p>
                    <div class="flex justify-end gap-2 pt-2">
                        <BaseButton type="button" variant="outline" size="sm" :disabled="form.processing" @click="editing = false">
                            Cancel
                        </BaseButton>
                        <BaseButton type="submit" variant="primary" size="sm" :loading="form.processing" :disabled="form.processing">
                            Save Changes
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <!-- Eligibility overview (read-only) -->
            <BaseCard class="lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900">Eligibility Requirements</h2>
                    <BaseBadge :variant="compliedCount === requirementsTotal ? 'success' : 'warning'">
                        {{ compliedCount }} / {{ requirementsTotal }} complied
                    </BaseBadge>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="compliancePercent === 100 ? 'bg-emerald-500' : 'bg-brand-500'"
                        :style="{ width: `${compliancePercent}%` }"
                    />
                </div>

                <ul class="mt-5 divide-y divide-slate-100">
                    <li v-for="req in requirements" :key="req.key" class="py-3">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
                                :class="req.complied
                                    ? 'bg-emerald-100 text-emerald-600'
                                    : req.submitted ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-400'"
                            >
                                <AppIcon
                                    :name="req.complied ? 'check' : req.submitted ? 'clock' : 'x-mark'"
                                    class="h-3.5 w-3.5"
                                />
                            </span>
                            <div class="min-w-0 flex-1">
                                <span class="text-sm text-slate-700">{{ req.label }}</span>
                                <p v-if="req.remarks" class="mt-0.5 text-xs text-slate-500">{{ req.remarks }}</p>
                                <p v-if="!req.complied && req.submitted" class="mt-0.5 text-xs text-amber-700">
                                    Document submitted — awaiting verification by your Field Office.
                                </p>
                            </div>

                            <label
                                v-if="!req.complied"
                                class="shrink-0 cursor-pointer text-xs font-medium text-brand-700 hover:underline"
                                :class="{ 'pointer-events-none opacity-50': uploadingKey === req.key }"
                            >
                                <input
                                    type="file"
                                    class="sr-only"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    @change="uploadRequirement(req, $event)"
                                >
                                <span class="inline-flex items-center gap-1">
                                    <AppIcon name="cloud-arrow-up" class="h-4 w-4" />
                                    {{ uploadingKey === req.key ? 'Uploading…' : req.submitted ? 'Replace' : 'Upload' }}
                                </span>
                            </label>
                        </div>

                        <p v-if="uploadErrors[req.key]" class="mt-1.5 pl-9 text-xs text-accent-600">
                            {{ uploadErrors[req.key] }}
                        </p>
                    </li>
                </ul>
                <p class="mt-4 text-xs text-slate-400">
                    You can submit supporting documents here (PDF, JPG or PNG, up to 5&nbsp;MB). Your
                    Field Office verifies each one — contact them about anything already verified.
                </p>
            </BaseCard>
        </div>
    </DashboardLayout>
</template>
