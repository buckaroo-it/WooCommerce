// Each card input is an iframe served from Buckaroo's PCI domain, so no theme CSS
// reaches it. Every field therefore sits on an inert input of the checkout's own
// markup (`.buckaroo-hosted-field__input`), which draws the border, background,
// height, focus and error states, and the iframe is laid over that input's text
// area. The SDK's baseStyling only has to make the iframe's own input draw the
// text, in the font and colours read back from the theme's input.

const TRANSPARENT_BACKGROUND = /^(transparent|rgba\(0,\s*0,\s*0,\s*0\))$/;

// A family name that is valid without quotes: one or more CSS identifiers.
const UNQUOTED_FAMILY = /^-?[a-z_][\w-]*(\s+-?[a-z_][\w-]*)*$/i;

const px = value => parseFloat(value) || 0;

// Hosted Fields escapes the quotes in a font stack (`"Segoe UI"`), which voids the
// whole declaration and leaves the iframe on the browser's default serif font, so
// the families are passed unquoted. A name that is only valid quoted is left out;
// the rest of the stack still applies.
function unquotedFontFamily(fontFamily) {
    return fontFamily
        .split(',')
        .map(family => family.trim().replace(/^(["'])(.*)\1$/, '$2'))
        .filter(family => UNQUOTED_FAMILY.test(family))
        .join(', ');
}

function themeInputFor(frame) {
    return frame?.parentElement?.querySelector('.buckaroo-hosted-field__input') ?? null;
}

// The SDK sizes its iframe from the frame once, at mount time, so the frame is
// placed over the theme input's text line first: inside its border and side
// padding, centred on its content box, and tall enough for the text not to clip
// when the theme's content box is a single line-height (WooCommerce Blocks).
function placeFrame(frame, style) {
    const top = px(style.borderTopWidth) + px(style.paddingTop);
    const bottom = px(style.borderBottomWidth) + px(style.paddingBottom);
    const contentHeight = px(style.height) - top - bottom;
    const slack = Math.max(px(style.fontSize) * 1.5 - contentHeight, 0) / 2;

    Object.assign(frame.style, {
        top: `${Math.max(top - slack, 0)}px`,
        bottom: `${Math.max(bottom - slack, 0)}px`,
        left: `${px(style.borderLeftWidth) + px(style.paddingLeft)}px`,
        right: `${px(style.borderRightWidth) + px(style.paddingRight)}px`,
    });
}

/**
 * Places the frame over its theme input and returns the SDK config styling
 * that matches it. Call right before mounting the field into `frameSelector`.
 */
export function hostedFieldStyling(frameSelector) {
    const frame = document.querySelector(frameSelector);
    const input = themeInputFor(frame);

    if (!input) {
        return { baseStyling: {} };
    }

    const style = window.getComputedStyle(input);
    placeFrame(frame, style);

    // The theme's placeholder colour lives on the pseudo element, which browsers
    // only resolve for an input that has a placeholder.
    if (!input.hasAttribute('placeholder')) {
        input.setAttribute('placeholder', ' ');
    }
    const placeholderColor = window.getComputedStyle(input, '::placeholder').color || style.color;

    return {
        baseStyling: {
            fontSize: style.fontSize,
            fontFamily: unquotedFontFamily(style.fontFamily) || undefined,
            fontStyle: 'normal',
            fontWeight: style.fontWeight,
            textAlign: 'left',
            textTransform: 'none',
            background: TRANSPARENT_BACKGROUND.test(style.backgroundColor) ? 'inherit' : style.backgroundColor,
            color: style.color,
            placeholderColor,
            border: 'none',
            borderRadius: '0',
            padding: '0',
            boxShadow: 'none',
        },
        cardLogoStyling: {
            height: '80%',
            position: 'absolute',
            border: 'none',
            borderRadius: style.borderRadius,
            opacity: '1',
            right: '0',
            backgroundColor: 'inherit',
        },
    };
}

/**
 * Mirrors the SDK's validation messages onto the theme input, so a field with
 * an error gets the checkout's own invalid styling: `has-error` in the Checkout
 * Block, `woocommerce-invalid` on the classic checkout's form row.
 */
export function syncHostedFieldErrors(errorIds) {
    errorIds.forEach(errorId => {
        const error = document.getElementById(errorId);
        const row = error?.closest('.buckaroo-hosted-field-row');

        if (!row) {
            return;
        }

        const invalid = error.textContent.trim() !== '';
        row.querySelector('.wc-block-components-text-input')?.classList.toggle('has-error', invalid);

        if (row.classList.contains('form-row')) {
            row.classList.toggle('woocommerce-invalid', invalid);
        }
    });
}
