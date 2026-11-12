// forms/formDefinitions.js — registry + behaviour binder for the Form engine.
// Each field dictionary lives in ./defs/<form>.js and receives `ctx` (see
// buildFormDefinitions). Pure-data forms (kva/ihale/product) carry no hooks;
// applyDefaults injects the standard oninput binding for them.
import Swal from 'sweetalert2';
import dayjs from 'dayjs';
import VMasker from 'vanilla-masker';
import { generatePassword } from '@/lib/password';
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import { usePermissionDataStore } from '@/stores/permissiondata';

import flatDef from './defs/flat.js';
import userDef from './defs/user.js';
import clientDef from './defs/client.js';
import ihaleDef from './defs/ihale.js';
import productDef from './defs/product.js';
import kvaDef from './defs/kva.js';
import productCategoryDef from './defs/product-category.js';
import offerDef from './defs/offer.js';
import orderDef from './defs/order.js';
import orderItemDef from './defs/order-item.js';
import requestDef from './defs/request.js';
import requestItemDef from './defs/request-item.js';
import requestfDef from './defs/requestf.js';
import requestfItemDef from './defs/requestf-item.js';

const DEFS = {
    'op-doc-flat-form'           : flatDef,
    'op-doc-user-form'           : userDef,
    'op-doc-client-form'         : clientDef,
    'op-doc-ihale-form'          : ihaleDef,
    'op-doc-product-form'        : productDef,
    'op-doc-kva-form'            : kvaDef,
    'op-doc-product-category-form': productCategoryDef,
    'op-doc-offer-form'          : offerDef,
    'op-doc-order-form'          : orderDef,
    'op-doc-order-item-form'     : orderItemDef,
    'op-doc-request-form'        : requestDef,
    'op-doc-request-item-form'   : requestItemDef,
    'op-doc-requestf-form'       : requestfDef,
    'op-doc-requestf-item-form'  : requestfItemDef,
};

const DEFAULT_INPUT_TYPES = new Set(['text', 'select', 'file', 'textarea', 'radio', 'date', 'password', 'number', 'email', 'switch']);

/** Inject the standard oninput binding for fields that carry none. */
function applyDefaults(form, ctx) {
    const defaultOninput = () => (e) => ctx.submitDynamicChanges(e.target);
    const walk = (fields) => {
        if (!Array.isArray(fields)) return;
        fields.forEach((f) => {
            if (!f) return;
            if (!f.oninput && DEFAULT_INPUT_TYPES.has(f.type)) {
                f.oninput = defaultOninput();
            }
            if (Array.isArray(f.subs)) walk(f.subs);
            if (Array.isArray(f.sub)) walk(f.sub);
        });
    };
    walk(form.fields);
    if (typeof form.oncreated !== 'function') form.oncreated = () => {};
    return form;
}

/** Shared client/müdürlük picker used by the client form. */
function usersClientClick(ctx) {
    return (e, element) => {
        Swal.fire({
            title: 'Cari / Müdürlük Seçiniz',
            showCloseButton: true,
            showConfirmButton: false,
            width: '920px',
            padding: '1.2rem 1.1rem 1rem',
            customClass: { popup: 'swal-client-picker', title: 'swal-client-title', htmlContainer: 'swal-client-html' },
            html: ` <style>
                        .swal-client-picker{ border-radius:16px !important; }
                        .swal-client-title{ font-size:17px !important; font-weight:800 !important; color:#0f172a !important; padding-bottom:10px !important; }
                        .swal-client-html{ margin:0 !important; }
                        #client-tree .pickletable{ border:1px solid #e2e8f0 !important; border-radius:12px !important; overflow:hidden !important; background:#fff !important; }
                        #client-tree .pickletable .divTable{ overflow-x:auto !important; }
                        #client-tree .pickletable table{ width:100% !important; min-width:720px !important; table-layout:fixed !important; }
                        #client-tree .pickletable th{ background:#f8fafc !important; color:#475569 !important; font-size:12px !important; font-weight:700 !important; letter-spacing:.04em !important; text-transform:uppercase !important; white-space:nowrap !important; overflow:visible !important; text-overflow:clip !important; padding:10px 12px !important; border-bottom:1px solid #e2e8f0 !important; }
                        #client-tree .pickletable th input{ width:100% !important; margin-top:6px !important; border-radius:8px !important; }
                        #client-tree .pickletable td{ font-size:13px !important; color:#0f172a !important; padding:11px 12px !important; white-space:nowrap !important; overflow:hidden !important; text-overflow:ellipsis !important; border-bottom:1px solid #f1f5f9 !important; }
                        #client-tree .pickletable td:first-child{ font-weight:600 !important; }
                        #client-tree .pickletable th:nth-child(1), #client-tree .pickletable td:nth-child(1){ width:34% !important; }
                        #client-tree .pickletable th:nth-child(2), #client-tree .pickletable td:nth-child(2){ width:22% !important; }
                        #client-tree .pickletable th:nth-child(3), #client-tree .pickletable td:nth-child(3){ width:22% !important; }
                        #client-tree .pickletable th:nth-child(4), #client-tree .pickletable td:nth-child(4){ width:14% !important; text-align:center !important; }
                        #client-tree .pickletable tbody tr:hover td{ background-color:#154b91 !important; color:white !important; }
                        #client-tree .pickletable tbody tr:hover{ border-radius:10px !important; }
                        #client-tree .pickletable tbody tr:hover td:first-child{ border-top-left-radius:10px !important; border-bottom-left-radius:10px !important; }
                        #client-tree .pickletable tbody tr:hover td:last-child{ border-top-right-radius:10px !important; border-bottom-right-radius:10px !important; }
                        #client-tree .pickletable .divPagination{ padding:12px 14px !important; border-top:1px solid #f1f5f9 !important; background:#fff !important; }
                        #client-tree .pickletable .divPagination button{ min-width:36px !important; height:36px !important; border-radius:8px !important; }
                    </style>
                    <div id="client-tree" class="text-start"></div>`,
            willOpen: () => {
                ctx.buildClientTable('#client-tree', (data) => {
                    const existingClients = document.querySelectorAll('input[name^="cliid"]');
                    let isDuplicate = false;
                    existingClients.forEach((input) => {
                        if (input.value === data.id.toString() && input.value !== '') isDuplicate = true;
                    });
                    if (isDuplicate) {
                        ctx.plib.toast(ctx.Swal, 'warning', 'Bu cari zaten eklenmiştir!');
                        return false;
                    }
                    const codeElm = element.parentNode.parentNode.parentNode.querySelector('input[name^="clicode"]');
                    const titleElm = element.parentNode.parentNode.parentNode.querySelector('input[name^="clititle"]');
                    const idElm = element.parentNode.parentNode.parentNode.querySelector('input[name^="cliid"]');
                    const sysElm = element.parentNode.parentNode.parentNode.querySelector('input[name^="client_system"]');
                    titleElm.value = data.title;
                    idElm.value = data.id;
                    codeElm.value = data.clicode;
                    if (sysElm) sysElm.value = (data.client_system || data.sys_code || 'GDZ').toString().toUpperCase();
                    codeElm.dispatchEvent(new Event('input'));
                    idElm.dispatchEvent(new Event('input'));
                    titleElm.dispatchEvent(new Event('input'));
                    if (sysElm) sysElm.dispatchEvent(new Event('input'));
                    Swal.close();
                });
            },
        });
    };
}

/**
 * Build the form dictionary for the component instance `vm`.
 * Each def file receives `ctx` and may use any of its members in callbacks.
 */
export function buildFormDefinitions(vm) {
    const ctx = {
        submitDynamicChanges: (el, isDatalist = false, datalistKey = null) => vm.submitDynamicChanges(el, isDatalist, datalistKey),
        plib: vm.plib,
        Swal: vm.Swal,
        buildClientTable: (selector, clickEvent) => vm.buildClientTable(selector, clickEvent),
        pickClient: (e, el) => usersClientClick(ctx)(e, el),
        dayjs,
        SwalLib: Swal,
        generatePassword,
        VMasker,
        useAuthStore,
        useNavigationStore,
        usePermissionDataStore,
    };

    const forms = {};
    for (const key of Object.keys(DEFS)) {
        forms[key] = applyDefaults(DEFS[key](ctx), ctx);
    }
    return forms;
}
