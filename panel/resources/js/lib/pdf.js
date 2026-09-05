/**
 * Shared PDF download helpers for order print forms.
 * Extracted from OForm.vue printMalzemeKabul / printMalzemeCinsMiktar.
 * 
 * ⚠️ SAFETY: These helpers ONLY handle the fetch→blob→download pattern.
 *    The actual PDF generation happens server-side (ExportController + blade templates).
 *    Changing these helpers does NOT affect PDF content — only the download mechanism.
 */

/**
 * Validate print form prerequisites. Returns null if OK, or an error string.
 * Caller shows the error via Swal.
 */
export function validatePrintForm({ itemTable, effectiveTransferMode, hasPartitions, selectedItems, canSend, imalatci }) {
    if (!itemTable || !itemTable.items || !itemTable.items.length) {
        return { title: 'Kalemler Yüklenmedi', text: 'Kalem bilgileri henüz yüklenemedi. Sayfayı yenileyip tekrar deneyin.' };
    }
    if (!effectiveTransferMode) {
        return { title: 'Transfer Türü Yok', text: 'Transfer türü bilgisi bulunamadı. Sayfayı yenileyip tekrar deneyin.' };
    }
    if (hasPartitions && effectiveTransferMode === 'at_once') {
        return { title: 'Tek Seferde Kilitli', text: 'Bu sipariş daha önce parçalı gönderildiği için artık sadece parçalı gönderim yapılabilir. Tüm parçalar silinirse tek seferde tekrar mümkün.' };
    }
    if (!imalatci) {
        return { title: 'İmalatçı Firma Boş', text: 'İmalatçı Firma adı boş. Formu yazdırmadan önce imalatçı firma bilgisi girmelisiniz.' };
    }
    if (effectiveTransferMode === 'partial' && !selectedItems.length && !canSend) {
        // DB mode: fall through to use all items
    } else if (effectiveTransferMode === 'partial' && !selectedItems.length) {
        return { title: 'Kalem Seçilmedi', text: 'Parçalı transfer seçtiniz ama henüz kalem işaretlemediniz. Gönderilecek kalemleri tablodan işaretleyin.' };
    }
    if (effectiveTransferMode === 'partial' && selectedItems.length) {
        const invalid = selectedItems.find(i => !i.amount || i.amount <= 0);
        if (invalid) {
            return { title: 'Bölme Miktarı Eksik', text: 'İşaretlenen kalemlerden birinin bölme miktarı girilmemiş. Tüm seçili kalemler için gönderilecek miktarı girin.' };
        }
    }
    return null;
}

/**
 * Download a PDF blob from a POST endpoint.
 * @param {string} endpoint - API URL (e.g. '/api/v1/export/malzeme-kabul')
 * @param {string} filename - Download filename (e.g. 'malzeme-kabul-3510004200.pdf')
 * @param {FormData} fd - Request body
 * @returns {Promise<void>}
 * @throws {Error} on HTTP error or non-PDF response
 */
export async function downloadPdf(endpoint, filename, fd) {
    const rsp = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Authorization': 'Bearer ' + (localStorage.getItem('token') || ''),
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
    });
    if (!rsp.ok) {
        const errData = await rsp.json().catch(() => ({}));
        throw new Error(errData.msg || 'PDF oluşturulamadı (HTTP ' + rsp.status + ')');
    }
    const ct = rsp.headers.get('content-type') || '';
    if (!ct.includes('pdf')) {
        const errData = await rsp.json().catch(() => ({}));
        throw new Error(errData.msg || 'Sunbekten PDF yerine hata döndü. Lütfen tekrar deneyin.');
    }
    const blob = await rsp.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    a.remove();
}
