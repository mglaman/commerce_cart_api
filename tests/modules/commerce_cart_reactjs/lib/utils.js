const langCode = drupalSettings.path.currentLanguage;

export const baseUrl = `${window.location.origin}${drupalSettings.path.baseUrl}`;

export function formatPrice(priceObject) {
  if (priceObject.currency_code === null) {
    return '';
  }
  return new Intl.NumberFormat(langCode, {style: 'currency', currency: priceObject.currency_code}).format(priceObject.number)
}
