/**
 * Parola uretimi ve dogrulamasi tek yerde.
 *
 * Karakter kumesi bilerek PASSWORD_PATTERN ile ayni: eski uretici Turkce harf
 * (cgiosu / CGIOSU) uretebiliyordu ama form dogrulamasi yalnizca ASCII kabul
 * ediyor, yani uretilen parola kendi validasyonundan gecemiyordu.
 */

const LOWERS = 'abcdefghijklmnopqrstuvwxyz';
const UPPERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const DIGITS = '0123456789';
const SPECIALS = '=!-@._*';
const ALL = LOWERS + UPPERS + DIGITS + SPECIALS;

/** UForm ve backend ile ayni kural: >=8, en az bir kucuk/buyuk/rakam/ozel karakter. */
export const PASSWORD_PATTERN = /^(?=.{8,}$)(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[=!\-@._*])[A-Za-z0-9=!\-@._*]+$/;

export function isValidPassword(value) {
    return PASSWORD_PATTERN.test(String(value ?? ''));
}

/**
 * Modulo sapmasi olmadan [0, max) araliginda kriptografik olarak guvenli tamsayi.
 * Math.random() tahmin edilebilir; uretilen parola bir hesabin tek kimlik bilgisi
 * oldugu icin burada kullanilmamali.
 */
function secureIndex(max) {
    const limit = Math.floor(0xffffffff / max) * max;
    const buf = new Uint32Array(1);

    let value;
    do {
        crypto.getRandomValues(buf);
        value = buf[0];
    } while (value >= limit);

    return value % max;
}

function pick(set) {
    return set.charAt(secureIndex(set.length));
}

/** Fisher-Yates; sort(() => Math.random() - 0.5) yanli bir karistirma uretir. */
function shuffle(chars) {
    for (let i = chars.length - 1; i > 0; i--) {
        const j = secureIndex(i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }

    return chars;
}

/**
 * Her karakter sinifindan en az bir tane iceren, PASSWORD_PATTERN'i saglayan parola.
 * @param {number} len en az 8
 */
export function generatePassword(len = 8) {
    const size = Math.max(8, len);

    const chars = [pick(LOWERS), pick(UPPERS), pick(DIGITS), pick(SPECIALS)];
    while (chars.length < size) chars.push(pick(ALL));

    return shuffle(chars).join('');
}
