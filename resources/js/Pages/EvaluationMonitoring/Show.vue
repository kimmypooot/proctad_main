<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import DashboardPageHeader from '@/Components/DashboardPageHeader.vue';

const props = defineProps({
    evaluation: { type: Object, required: true },
    criteria: { type: Object, required: true },
});

const designationLabels = {
    chief_examiner: 'Chief Examiner',
    supervising_examiner: 'Supervising Examiner',
    proctor: 'Room Proctor',
    room_examiner: 'Room Examiner',
};

const designationLabel = computed(() => designationLabels[props.evaluation.designation] ?? props.evaluation.designation);
const isSupervisingExaminer = computed(() => props.evaluation.designation === 'supervising_examiner');
const isChiefExaminer = computed(() => props.evaluation.designation === 'chief_examiner');
const isRoomRole = computed(() => ['proctor', 'room_examiner'].includes(props.evaluation.designation));
const showAdministration = computed(() => isSupervisingExaminer.value || isChiefExaminer.value);

const overallRatingLabel = computed(() => props.criteria.overall_rating_options[props.evaluation.overall_rating] ?? null);
</script>

<template>
    <Head title="Evaluation Details" />

    <DashboardLayout>
        <DashboardPageHeader :title="evaluation.respondent_name" :subtitle="designationLabel">
            <template #back>
                <Link href="/evaluation-monitoring" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-700">
                    <AppIcon name="chevron-left" class="h-4 w-4" /> Back to Evaluation Monitoring
                </Link>
            </template>
        </DashboardPageHeader>

        <!-- Header card -->
        <div class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Examination</p>
                <p class="mt-0.5 text-sm text-slate-900">{{ evaluation.examination?.title }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Testing Center</p>
                <p class="mt-0.5 text-sm text-slate-900">{{ evaluation.field_office?.name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">School</p>
                <p class="mt-0.5 text-sm text-slate-900">{{ evaluation.school?.name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Submitted</p>
                <p class="mt-0.5 text-sm text-slate-900">{{ evaluation.submitted_at }}</p>
            </div>
        </div>

        <!-- Room ratings (Supervising Examiner) -->
        <div v-if="isSupervisingExaminer && evaluation.room_ratings?.length" class="mt-6 space-y-4">
            <h2 class="text-base font-semibold text-slate-900">Room Examiner / Proctor Ratings</h2>
            <div v-for="(rating, index) in evaluation.room_ratings" :key="index" class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-slate-900">{{ rating.ratee_name }}</p>
                    <!-- Not prefixed: room_number already reads "Room-001". -->
                    <BaseBadge variant="neutral">{{ rating.room_no }}</BaseBadge>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div v-for="(group, label) in { Punctuality: rating.punctuality, Decorum: rating.decorum, Procedures: rating.procedures }" :key="label">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ label }}</p>
                        <p class="mt-0.5 text-sm text-slate-700">
                            {{ (group.reduce((a, b) => a + b, 0) / group.length).toFixed(1) }}/5 average
                        </p>
                    </div>
                </div>
                <p v-if="rating.comment" class="mt-3 text-sm text-slate-600">{{ rating.comment }}</p>
            </div>
        </div>

        <!-- Room Readiness -->
        <div v-if="evaluation.room_readiness" class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-base font-semibold text-slate-900">Room Readiness</h2>
            <ul class="mt-4 space-y-2">
                <li v-for="(statement, index) in criteria.room_readiness" :key="index" class="flex items-start gap-2 text-sm">
                    <AppIcon
                        :name="evaluation.room_readiness[index] ? 'check-circle' : 'x-circle'"
                        class="mt-0.5 h-4 w-4 shrink-0"
                        :class="evaluation.room_readiness[index] ? 'text-emerald-600' : 'text-slate-300'"
                    />
                    <span :class="evaluation.room_readiness[index] ? 'text-slate-700' : 'text-slate-400'">{{ statement }}</span>
                </li>
            </ul>
        </div>

        <!-- Exam Preparation (Room Examiner/Proctor) -->
        <div v-if="isRoomRole && evaluation.exam_preparation" class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-base font-semibold text-slate-900">Exam Preparation</h2>
            <ul class="mt-4 space-y-2">
                <li v-for="(statement, index) in criteria.exam_preparation" :key="index" class="flex items-center justify-between text-sm">
                    <span class="text-slate-700">{{ statement }}</span>
                    <BaseBadge v-if="evaluation.exam_preparation[index] != null" variant="brand">{{ evaluation.exam_preparation[index] }}/5</BaseBadge>
                    <span v-else class="text-slate-300">—</span>
                </li>
            </ul>
        </div>

        <!-- Examination Administration (Chief Examiner / Supervising Examiner) -->
        <template v-if="showAdministration">
            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">Examination Administration</h2>

                <div
                    v-for="section in [
                        { key: 'venue_readiness', label: 'Venue Readiness', comment: 'venue_comment' },
                        { key: 'committee_coordination', label: 'Examination Committee Performance and Coordination', comment: 'committee_comment' },
                        { key: 'conduct_of_exam', label: 'Conduct of Examination', comment: 'conduct_comment' },
                        { key: 'examinee_experience', label: 'Examinee Experience', comment: 'examinee_comment' },
                    ]"
                    :key="section.key"
                    class="mt-5"
                >
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-brand-700">{{ section.label }}</h3>
                    <ul v-if="evaluation[section.key]" class="mt-2 space-y-2">
                        <li v-for="(statement, index) in criteria[section.key]" :key="index" class="flex items-center justify-between text-sm">
                            <span class="text-slate-700">{{ statement }}</span>
                            <BaseBadge v-if="evaluation[section.key][index] != null" variant="brand">{{ evaluation[section.key][index] }}/5</BaseBadge>
                            <span v-else class="text-slate-300">—</span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-slate-400">Not rated.</p>
                    <p v-if="evaluation[section.comment]" class="mt-2 text-sm text-slate-600">{{ evaluation[section.comment] }}</p>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">Overall Assessment and Recommendations</h2>
                <p v-if="overallRatingLabel" class="mt-2 text-sm text-slate-700">{{ overallRatingLabel }}</p>

                <dl class="mt-4 space-y-4">
                    <div v-if="evaluation.what_worked">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">What worked effectively</dt>
                        <dd class="mt-0.5 text-sm text-slate-700">{{ evaluation.what_worked }}</dd>
                    </div>
                    <div v-if="evaluation.challenges">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Challenges or issues</dt>
                        <dd class="mt-0.5 text-sm text-slate-700">{{ evaluation.challenges }}</dd>
                    </div>
                    <div v-if="evaluation.improvements">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Areas to improve</dt>
                        <dd class="mt-0.5 text-sm text-slate-700">{{ evaluation.improvements }}</dd>
                    </div>
                    <div v-if="evaluation.suggestions">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Suggestions</dt>
                        <dd class="mt-0.5 text-sm text-slate-700">{{ evaluation.suggestions }}</dd>
                    </div>
                </dl>
            </div>
        </template>
    </DashboardLayout>
</template>
