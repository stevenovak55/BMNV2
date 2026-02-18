/**
 * Property Utilities — shared formatters used by both list and map search.
 *
 * V2 field names: price, beds, baths, sqft, status, address
 */

declare const bmnTheme: {
  homeUrl: string;
};

/** Format numeric price → $XXK / $X.XXM */
export function formatPrice(price: number): string {
  if (!price) return '';
  if (price >= 1_000_000) return '$' + (price / 1_000_000).toFixed(2) + 'M';
  if (price >= 1_000) return '$' + Math.round(price / 1_000) + 'K';
  return '$' + price.toLocaleString();
}

/** DOM-based HTML escaping */
export function escapeHtml(text: string): string {
  const div = document.createElement('div');
  div.textContent = text || '';
  return div.innerHTML;
}

/** Build canonical property URL from listing_id */
export function getPropertyUrl(listingId: string): string {
  return bmnTheme.homeUrl + 'property/' + listingId + '/';
}

/** Status → Tailwind color classes */
export function getStatusColor(status: string): { bg: string; text: string } {
  switch (status) {
    case 'Active':
      return { bg: 'bg-green-100', text: 'text-green-800' };
    case 'Pending':
    case 'Active Under Contract':
      return { bg: 'bg-yellow-100', text: 'text-yellow-800' };
    case 'Closed':
    case 'Sold':
      return { bg: 'bg-red-100', text: 'text-red-800' };
    default:
      return { bg: 'bg-gray-100', text: 'text-gray-800' };
  }
}

/** Days on market → badge info */
export function getDomLabel(dom: number | string): { text: string; isNew: boolean } {
  const d = typeof dom === 'string' ? parseInt(dom, 10) : dom;
  if (!d && d !== 0) return { text: '', isNew: false };
  if (d < 7) return { text: 'New', isNew: true };
  return { text: `${d} days`, isNew: false };
}
