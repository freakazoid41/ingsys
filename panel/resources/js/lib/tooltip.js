/**
 * Hafif tooltip yardımcısı.
 *
 * Neden CSS ::after değil: PickleTable hücreleri `.pickletable td { overflow: hidden !important }`
 * ile kırpılıyor ve #div_table yatay kaydırma için `overflow-x: auto` taşıyor. Hücre içinde
 * konumlanan bir tooltip görünmeden kırpılır. Bu yüzden tooltip body'ye eklenip
 * position:fixed ile konumlandırılıyor — hiçbir overflow bağlamına takılmaz.
 */

const STYLE_ID = 'coal-tooltip-style';
let tipEl = null;

function ensureStyle() {
    if (document.getElementById(STYLE_ID)) return;

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
        .coal-tooltip {
            position: fixed;
            z-index: 20000;
            display: none;
            padding: 5px 9px;
            border-radius: 6px;
            background: #1f2937;
            color: #fff;
            font-size: .72rem;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            pointer-events: none;
            box-shadow: 0 6px 18px rgba(15,23,42,.18);
        }
        .coal-tooltip::after {
            content: '';
            position: absolute;
            left: 50%;
            margin-left: -4px;
            border: 4px solid transparent;
        }
        .coal-tooltip[data-placement="top"]::after {
            top: 100%;
            border-top-color: #1f2937;
        }
        .coal-tooltip[data-placement="bottom"]::after {
            bottom: 100%;
            border-bottom-color: #1f2937;
        }
        /* disabled butonlarda Bootstrap pointer-events:none veriyor; o zaman mouseenter
           hiç tetiklenmez ve tooltip çıkmaz. Tooltip'li butonlarda hover'ı geri açıyoruz. */
        [data-tooltip]:disabled {
            pointer-events: auto !important;
        }
    `;
    document.head.appendChild(style);
}

function ensureEl() {
    ensureStyle();

    if (!tipEl || !document.body.contains(tipEl)) {
        tipEl = document.createElement('div');
        tipEl.className = 'coal-tooltip';
        document.body.appendChild(tipEl);
    }

    return tipEl;
}

export function hideTooltip() {
    if (tipEl) tipEl.style.display = 'none';
}

function showTooltip(el, label) {
    if (!el.isConnected) return;

    const tip = ensureEl();
    tip.textContent = label;
    tip.style.display = 'block';
    tip.style.visibility = 'hidden';

    const anchor = el.getBoundingClientRect();
    const box = tip.getBoundingClientRect();

    let placement = 'top';
    let top = anchor.top - box.height - 8;
    if (top < 8) {
        placement = 'bottom';
        top = anchor.bottom + 8;
    }

    // ekran dışına taşmayı engelle
    let left = anchor.left + anchor.width / 2 - box.width / 2;
    left = Math.max(8, Math.min(left, window.innerWidth - box.width - 8));

    tip.dataset.placement = placement;
    tip.style.top = `${top}px`;
    tip.style.left = `${left}px`;
    tip.style.visibility = 'visible';

    window.addEventListener('scroll', hideTooltip, { passive: true, once: true, capture: true });
}

/**
 * Elemana tooltip bağlar. Metin verilmezse mevcut title özniteliğini kullanır ve
 * çift tooltip çıkmaması için native title'ı kaldırır.
 *
 * @param {HTMLElement} el
 * @param {string|null} text
 * @returns {HTMLElement} zincirleme kullanım için elemanın kendisi
 */
export function attachTooltip(el, text = null) {
    const label = text ?? el.getAttribute('title') ?? '';
    if (!el || !label) return el;

    el.removeAttribute('title');
    el.dataset.tooltip = label;

    el.addEventListener('mouseenter', () => showTooltip(el, label));
    el.addEventListener('mouseleave', hideTooltip);
    el.addEventListener('click', hideTooltip);

    return el;
}
