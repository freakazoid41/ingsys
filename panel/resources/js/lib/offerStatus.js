/**
 * An offer carries two independent states:
 *   - process state, derived from the transactions table  (doc_trans_offer_*)
 *   - document activeness, documents.status               (document_status)
 *
 * For offers document_status = 0 means "İptal Edildi" and it overrides whatever the last
 * transaction was. Every screen must resolve the badge through here so the two axes are
 * combined identically everywhere.
 */

export const OFFER_CANCELLED_KEY = 'cancelled';

/**
 * Filter entry that makes the backend include cancelled offers in a listing.
 * Without it the query stays active-only, which is what dashboards and notification
 * badges rely on — never add it to a "pending work" query.
 */
export const WITH_CANCELLED_FILTER = { key: 'with-cancelled', type: '=', value: '1' };

function variantFor(key) {
    switch (key) {
        case 'doc_trans_offer_approved':
            return 'success';
        case 'doc_trans_offer_rejected':
            return 'danger';
        case 'doc_trans_offer_revision':
        case 'doc_trans_offer_revised':
        case 'doc_trans_offer_review':
            return 'warning';
        default:
            return 'secondary';
    }
}

/**
 * @param {object} row a listing row or a document detail object
 * @returns {{key: string, label: string, variant: string, terminal: boolean}}
 *          terminal = true means the offer accepts no further edits or status changes
 */
export function offerStatus(row) {
    if (String(row?.document_status ?? '1') === '0') {
        return {
            key: OFFER_CANCELLED_KEY,
            label: 'İptal Edildi',
            variant: 'danger',
            terminal: true,
        };
    }

    const parts = String(row?.status ?? '').split('**');
    const key = parts[0] || 'doc_trans_offer_sended';

    return {
        key,
        label: parts[1] || 'Teklif Gönderildi',
        variant: variantFor(key),
        terminal: false,
    };
}

export function isOfferCancelled(row) {
    return offerStatus(row).terminal;
}
