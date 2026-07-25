/**
 * Field key → the wording used on the control itself, so FormErrorSummary names
 * a rejected field exactly as the user sees it on the form. Shared by every
 * surface that renders MemberForm (the edit modal, the examination roster).
 *
 * Keep in step with the labels in MemberForm.vue.
 */
export const memberFieldLabels = {
    first_name: 'First Name',
    middle_name: 'Middle Name',
    last_name: 'Last Name',
    suffix: 'Suffix',
    sex: 'Sex',
    date_of_birth: 'Date of Birth',
    email: 'Email Address',
    mobile_number: 'Mobile Number',
    agency: 'Agency',
    position: 'Position',
    field_office_id: 'Field Office',
    testing_center_id: 'Testing Center',
    photo: 'ID Photo',
    status: 'Accreditation Status',
    disqualification_remarks: 'Disqualification Remarks',
};
