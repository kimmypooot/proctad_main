<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseButton from '@/Components/BaseButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TextArea from '@/Components/TextArea.vue';
import SelectInput from '@/Components/SelectInput.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import RatingGrid from '@/Components/RatingGrid.vue';
import AppIcon from '@/Components/AppIcon.vue';
import SectionTitle from '@/Components/SectionTitle.vue';

const props = defineProps({
    examinations: { type: Array, required: true },
    criteria: { type: Object, required: true },
});

const flashSuccess = computed(() => usePage().props.flash?.success);

const examinationId = ref('');
const searchQuery = ref('');
const searchResults = ref([]);
const searching = ref(false);
const searchError = ref(null);
let searchTimer = null;

const resolved = ref(null); // { exam_assignment_id, respondent_name, designation, field_office, school, room_no }
const resolving = ref(false);

const emptyRoomRating = () => ({
    exam_assignment_id: null,
    room_no: '',
    ratee_name: '',
    punctuality: Array(props.criteria.punctuality.length).fill(null),
    decorum: Array(props.criteria.decorum.length).fill(null),
    procedures: Array(props.criteria.procedures.length).fill(null),
    comment: '',
});

const form = useForm({
    examination_id: '',
    exam_assignment_id: '',
    room_ratings: [],
    room_readiness: Array(props.criteria.room_readiness.length).fill(false),
    exam_preparation: Array(props.criteria.exam_preparation.length).fill(null),
    venue_readiness: Array(props.criteria.venue_readiness.length).fill(null),
    venue_comment: '',
    committee_coordination: Array(props.criteria.committee_coordination.length).fill(null),
    committee_comment: '',
    conduct_of_exam: Array(props.criteria.conduct_of_exam.length).fill(null),
    conduct_comment: '',
    examinee_experience: Array(props.criteria.examinee_experience.length).fill(null),
    examinee_comment: '',
    overall_rating: '',
    what_worked: '',
    challenges: '',
    improvements: '',
    suggestions: '',
});

const isSupervisingExaminer = computed(() => resolved.value?.designation?.value === 'supervising_examiner');
const isChiefExaminer = computed(() => resolved.value?.designation?.value === 'chief_examiner');
const isRoomRole = computed(() => ['proctor', 'room_examiner'].includes(resolved.value?.designation?.value));
const showAdministrationSection = computed(() => isSupervisingExaminer.value || isChiefExaminer.value);

const resetSelection = () => {
    resolved.value = null;
    searchQuery.value = '';
    searchResults.value = [];
    form.exam_assignment_id = '';
    form.room_ratings = [];
};

watch(examinationId, () => resetSelection());

watch(searchQuery, (value) => {
    clearTimeout(searchTimer);
    searchError.value = null;

    if (!examinationId.value || value.trim().length < 2) {
        searchResults.value = [];
        return;
    }

    searchTimer = setTimeout(async () => {
        searching.value = true;
        try {
            const params = new URLSearchParams({ examination_id: examinationId.value, q: value.trim() });
            const response = await fetch(`/evaluation/search?${params}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Search failed');
            const data = await response.json();
            searchResults.value = data.results;
        } catch {
            searchError.value = 'Something went wrong searching — please try again.';
        } finally {
            searching.value = false;
        }
    }, 300);
});

const selectResult = async (result) => {
    resolving.value = true;
    searchError.value = null;
    try {
        const response = await fetch(`/evaluation/assignments/${result.id}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Resolve failed');
        const data = await response.json();

        resolved.value = data;
        form.examination_id = examinationId.value;
        form.exam_assignment_id = data.exam_assignment_id;
        searchResults.value = [];
        searchQuery.value = '';

        if (data.designation.value === 'supervising_examiner') {
            form.room_ratings = data.subordinates.length
                ? data.subordinates.map((s) => ({ ...emptyRoomRating(), ...s }))
                : [emptyRoomRating()];
        }
    } catch {
        searchError.value = 'Could not load that assignment — please try again.';
    } finally {
        resolving.value = false;
    }
};

const addRoomRating = () => form.room_ratings.push(emptyRoomRating());
const removeRoomRating = (index) => form.room_ratings.splice(index, 1);

const overallRatingOptions = computed(() =>
    [5, 4, 3, 2, 1].map((value) => ({ value, label: props.criteria.overall_rating_options[value] })),
);

const examinationOptions = computed(() =>
    props.examinations.map((e) => ({ value: e.id, label: `${e.title} — ${e.exam_date}` })),
);

const submit = () => {
    form.post('/evaluation', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            examinationId.value = '';
            resetSelection();
        },
    });
};
</script>

<template>
    <PublicLayout>
        <Head title="Post-Examination Evaluation" />

        <section class="mx-auto max-w-3xl px-4 py-12 sm:py-16">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">PROCTAD Post-Examination Evaluation</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Test Administrator Performance Evaluation</h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                Please give your sincerest and honest observation. Your response shall serve as management information for
                future examination administration. All responses are confidential.
            </p>

            <BaseAlert v-if="flashSuccess" class="mt-6" variant="success">{{ flashSuccess }}</BaseAlert>

            <form v-else class="mt-8 space-y-10" @submit.prevent="submit">
                <!-- Step 1: Examination -->
                <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <SectionTitle icon="calendar" label="Select the Examination" />
                    <SelectInput
                        v-model="examinationId"
                        label="Examination"
                        required
                        :options="examinationOptions"
                        :error="form.errors.examination_id"
                    />
                </div>

                <!-- Step 2: Find yourself -->
                <div v-if="examinationId && !resolved" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <SectionTitle icon="identification" label="Find Your Assignment" />
                    <p class="text-sm text-slate-600">
                        Search your name or PROCTAD ID to load your designation and testing assignment automatically.
                    </p>

                    <TextInput v-model="searchQuery" label="Your name or PROCTAD ID" placeholder="e.g. Juan Dela Cruz" />

                    <p v-if="searching" class="text-sm text-slate-400">Searching…</p>
                    <BaseAlert v-if="searchError" variant="error">{{ searchError }}</BaseAlert>

                    <ul v-if="searchResults.length" class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                        <li v-for="result in searchResults" :key="result.id">
                            <button
                                type="button"
                                class="flex w-full flex-col gap-0.5 px-4 py-3 text-left transition-colors hover:bg-brand-50"
                                @click="selectResult(result)"
                            >
                                <span class="text-sm font-semibold text-slate-900">{{ result.name }}</span>
                                <span class="text-xs text-slate-500">
                                    {{ result.role_label }}
                                    <span v-if="result.room_no"> — {{ result.room_no }}</span>
                                    <span v-if="result.school_name"> — {{ result.school_name }}</span>
                                </span>
                            </button>
                        </li>
                    </ul>
                    <p
                        v-else-if="searchQuery.trim().length >= 2 && !searching"
                        class="text-sm text-slate-500"
                    >
                        No confirmed assignment matched that search for this examination.
                    </p>
                </div>

                <!-- Confirmation card once resolved -->
                <div v-if="resolved" class="space-y-3 rounded-xl border border-brand-200 bg-brand-50 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ resolved.respondent_name }}</p>
                            <p class="mt-0.5 text-xs text-slate-600">
                                {{ resolved.designation.label }}
                                <span v-if="resolved.room_no"> — {{ resolved.room_no }}</span>
                            </p>
                            <p class="text-xs text-slate-600">
                                <span v-if="resolved.field_office">{{ resolved.field_office.name }}</span>
                                <span v-if="resolved.school"> — {{ resolved.school.name }}</span>
                            </p>
                        </div>
                        <BaseButton variant="link" type="button" @click="resetSelection">Not you? Search again</BaseButton>
                    </div>
                    <p
                        v-if="isSupervisingExaminer && form.room_ratings.every((r) => !r.exam_assignment_id)"
                        class="text-xs text-amber-700"
                    >
                        We couldn't automatically find your room roster — please add the Room Examiners/Proctors you supervised below.
                    </p>
                </div>

                <p v-if="resolving" class="text-sm text-slate-500">Loading your assignment…</p>

                <template v-if="resolved">
                    <!-- Supervising Examiner: rate each Room Examiner / Proctor -->
                    <div v-if="isSupervisingExaminer" class="space-y-6">
                        <SectionTitle icon="users" label="Evaluation of Room Examiners/Proctors" />
                        <p class="text-sm text-slate-600">
                            Using the rating scale from 1 (Poor) to 5 (Excellent), rate every Room Examiner/Proctor under your supervision.
                        </p>

                        <div
                            v-for="(rating, index) in form.room_ratings"
                            :key="index"
                            class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Room Examiner/Proctor #{{ index + 1 }}</h3>
                                <BaseButton
                                    v-if="form.room_ratings.length > 1"
                                    variant="link-accent"
                                    type="button"
                                    @click="removeRoomRating(index)"
                                >
                                    <AppIcon name="trash" class="h-4 w-4" /> Remove
                                </BaseButton>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <TextInput
                                    v-model="rating.room_no"
                                    label="Room No."
                                    required
                                    :error="form.errors[`room_ratings.${index}.room_no`]"
                                />
                                <TextInput
                                    v-model="rating.ratee_name"
                                    label="Name of Room Examiner/Proctor"
                                    required
                                    :error="form.errors[`room_ratings.${index}.ratee_name`]"
                                />
                            </div>

                            <RatingGrid
                                v-model="rating.punctuality"
                                title="Punctuality"
                                :statements="criteria.punctuality"
                                :error="form.errors[`room_ratings.${index}.punctuality`]"
                            />
                            <RatingGrid
                                v-model="rating.decorum"
                                title="Decorum"
                                :statements="criteria.decorum"
                                :error="form.errors[`room_ratings.${index}.decorum`]"
                            />
                            <RatingGrid
                                v-model="rating.procedures"
                                title="Procedures"
                                :statements="criteria.procedures"
                                :error="form.errors[`room_ratings.${index}.procedures`]"
                            />

                            <TextArea
                                v-model="rating.comment"
                                label="Comment on the Room Examiner/Proctor's Performance"
                                :error="form.errors[`room_ratings.${index}.comment`]"
                            />
                        </div>

                        <BaseButton variant="outline" type="button" @click="addRoomRating">
                            <AppIcon name="plus" class="h-4 w-4" /> Rate another Room Examiner/Proctor
                        </BaseButton>
                    </div>

                    <!-- Room Readiness checklist: Supervising Examiner + Room Examiner/Proctor -->
                    <div v-if="isSupervisingExaminer || isRoomRole" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <SectionTitle icon="building-library" label="Room Readiness" />
                        <p class="text-sm text-slate-600">Check every standard that was met for the examination room.</p>

                        <div class="space-y-3">
                            <CheckboxInput
                                v-for="(statement, index) in criteria.room_readiness"
                                :key="index"
                                v-model="form.room_readiness[index]"
                                :error="form.errors[`room_readiness.${index}`]"
                            >
                                {{ statement }}
                            </CheckboxInput>
                        </div>
                        <p v-if="form.errors.room_readiness" class="text-sm text-accent-600" role="alert">{{ form.errors.room_readiness }}</p>
                    </div>

                    <!-- Exam Preparation: Room Examiner/Proctor only -->
                    <div v-if="isRoomRole" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <SectionTitle icon="clipboard-check" label="Exam Preparation" />
                        <RatingGrid
                            v-model="form.exam_preparation"
                            :statements="criteria.exam_preparation"
                            :error="form.errors.exam_preparation"
                        />
                    </div>

                    <!-- Examination Administration: Chief Examiner + Supervising Examiner -->
                    <template v-if="showAdministrationSection">
                        <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <SectionTitle icon="star" label="Examination Administration" />
                            <p class="text-sm text-slate-600">
                                Please share your insights on the overall examination administration, including venue readiness,
                                staff performance, and examinee experience.
                            </p>

                            <RatingGrid
                                v-model="form.venue_readiness"
                                title="Venue Readiness"
                                :statements="criteria.venue_readiness"
                                :error="form.errors.venue_readiness"
                            />
                            <TextArea v-model="form.venue_comment" label="Comments on the venue preparation" optional :error="form.errors.venue_comment" />

                            <RatingGrid
                                v-model="form.committee_coordination"
                                title="Examination Committee Performance and Coordination"
                                :statements="criteria.committee_coordination"
                                :error="form.errors.committee_coordination"
                            />
                            <TextArea
                                v-model="form.committee_comment"
                                label="Did you experience any problems related to personnel or coordination?"
                                optional
                                :error="form.errors.committee_comment"
                            />

                            <RatingGrid
                                v-model="form.conduct_of_exam"
                                title="Conduct of Examination"
                                :statements="criteria.conduct_of_exam"
                                :error="form.errors.conduct_of_exam"
                            />
                            <TextArea
                                v-model="form.conduct_comment"
                                label="Were there any issues encountered during the conduct of the exam?"
                                optional
                                :error="form.errors.conduct_comment"
                            />

                            <RatingGrid
                                v-model="form.examinee_experience"
                                title="Examinee Experience"
                                :statements="criteria.examinee_experience"
                                :error="form.errors.examinee_experience"
                            />
                            <TextArea
                                v-model="form.examinee_comment"
                                label="Did examinees report any concerns about the venue or the process?"
                                optional
                                :error="form.errors.examinee_comment"
                            />
                        </div>

                        <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <SectionTitle icon="document-check" label="Overall Assessment and Recommendations" />

                            <SelectInput
                                v-model="form.overall_rating"
                                label="Overall, how would you rate the conduct of the examination at your testing center?"
                                required
                                :options="overallRatingOptions"
                                :error="form.errors.overall_rating"
                            />
                            <TextArea v-model="form.what_worked" label="What worked effectively during the exam?" optional :error="form.errors.what_worked" />
                            <TextArea
                                v-model="form.challenges"
                                label="What challenges or issues would you recommend addressing for future exams?"
                                optional
                                :error="form.errors.challenges"
                            />
                            <TextArea
                                v-model="form.improvements"
                                label="What areas would you recommend improving for future examinations?"
                                optional
                                :error="form.errors.improvements"
                            />
                            <TextArea
                                v-model="form.suggestions"
                                label="What suggestions do you have to further improve venue readiness, staff performance, or the overall examinee experience?"
                                optional
                                :error="form.errors.suggestions"
                            />
                        </div>
                    </template>

                    <BaseButton
                        type="submit"
                        variant="primary"
                        size="lg"
                        block
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        Submit Evaluation
                    </BaseButton>
                </template>
            </form>
        </section>
    </PublicLayout>
</template>
