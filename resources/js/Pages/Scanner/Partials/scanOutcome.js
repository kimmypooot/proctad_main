/**
 * The verdict of a scan, and how it is dressed.
 *
 * Shared by the two surfaces that announce it — ScanResultHero at the top of
 * the page, and ScanVerdictOverlay across the viewfinder — because they are the
 * same answer to the same question, and an operator who learns "amber means
 * wrong venue" from one must not meet a different amber in the other.
 */

/** @returns {string} one of the keys of SCAN_STATES */
export const outcomeOf = ({ notFound, outOfReach, attendance, result, oepResult }) => {
    // Ahead of not_found: the code did match somebody, just not somebody this
    // link may record. Telling the two apart is the whole point of the state.
    if (outOfReach) return 'out_of_reach';
    if (notFound) return 'not_found';
    if (attendance?.outcome) return attendance.outcome;
    if (result || oepResult) return 'identity';

    return 'idle';
};

/**
 * Colour carries the verdict before any text is read, so each state gets a
 * distinct hue rather than a shade of the same one. Paired with an icon and
 * wording — colour alone would fail anyone with a colour-vision deficiency,
 * and these are read fast in bad lighting.
 */
export const SCAN_STATES = {
    confirmed: {
        ring: 'border-emerald-300 bg-gradient-to-br from-emerald-50 to-emerald-100/60',
        chip: 'bg-emerald-600 text-white',
        overlay: 'bg-emerald-600/95',
        icon: 'check-circle',
        iconClass: 'text-emerald-600',
        heading: 'Attendance recorded',
    },
    already_confirmed: {
        ring: 'border-sky-300 bg-gradient-to-br from-sky-50 to-sky-100/60',
        chip: 'bg-sky-600 text-white',
        overlay: 'bg-sky-600/95',
        icon: 'check-badge',
        iconClass: 'text-sky-600',
        heading: 'Already checked in',
    },
    venue_required: {
        ring: 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100/60',
        chip: 'bg-amber-500 text-white',
        overlay: 'bg-amber-600/95',
        icon: 'exclamation-triangle',
        iconClass: 'text-amber-600',
        heading: 'Venue needed',
    },
    wrong_venue: {
        ring: 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100/60',
        chip: 'bg-amber-500 text-white',
        overlay: 'bg-amber-600/95',
        icon: 'exclamation-triangle',
        iconClass: 'text-amber-600',
        heading: 'Wrong venue',
    },
    not_assigned: {
        ring: 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100/60',
        chip: 'bg-amber-500 text-white',
        overlay: 'bg-amber-600/95',
        icon: 'exclamation-triangle',
        iconClass: 'text-amber-600',
        heading: 'Not on this roster',
    },
    // Amber, with the other "this scanner cannot do that" states, rather than
    // red: nothing is wrong with the person or the code, only with the pairing
    // of the two to this link.
    out_of_reach: {
        ring: 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100/60',
        chip: 'bg-amber-500 text-white',
        overlay: 'bg-amber-600/95',
        icon: 'exclamation-triangle',
        iconClass: 'text-amber-600',
        heading: 'Outside this scanner',
    },
    members_only: {
        ring: 'border-amber-300 bg-gradient-to-br from-amber-50 to-amber-100/60',
        chip: 'bg-amber-500 text-white',
        overlay: 'bg-amber-600/95',
        icon: 'exclamation-triangle',
        iconClass: 'text-amber-600',
        heading: 'Members only',
    },
    not_found: {
        ring: 'border-accent-300 bg-gradient-to-br from-accent-50 to-accent-100/60',
        chip: 'bg-accent-600 text-white',
        overlay: 'bg-accent-600/95',
        icon: 'x-circle',
        iconClass: 'text-accent-600',
        heading: 'No record found',
    },
    identity: {
        ring: 'border-brand-200 bg-gradient-to-br from-brand-50 to-brand-100/60',
        chip: 'bg-brand-600 text-white',
        overlay: 'bg-brand-700/95',
        icon: 'identification',
        iconClass: 'text-brand-600',
        heading: 'Identity verified',
    },
    idle: {
        ring: 'border-dashed border-slate-300 bg-white',
        chip: 'bg-slate-200 text-slate-600',
        overlay: 'bg-slate-800/95',
        icon: 'qr-code',
        iconClass: 'text-slate-400',
        heading: 'Ready to scan',
    },
};

export const scanState = (outcome) => SCAN_STATES[outcome] ?? SCAN_STATES.idle;
