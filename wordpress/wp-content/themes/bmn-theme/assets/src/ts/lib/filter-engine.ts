/**
 * Filter Engine — single source of truth for search filter state,
 * serialization, chip rendering, and reset logic.
 *
 * Pure functions — no Alpine, DOM, or fetch dependency.
 */

export interface SearchFilters {
  city: string;
  neighborhood: string;
  address: string;
  street: string;
  min_price: string;
  max_price: string;
  beds: string;
  baths: string;
  property_type: string[];
  status: string[];
  school_grade: string;
  price_reduced: string;
  new_listing_days: string;
  sort: string;
  page: number;
  // Advanced filters
  sqft_min: string;
  sqft_max: string;
  lot_size_min: string;
  lot_size_max: string;
  year_built_min: string;
  year_built_max: string;
  max_dom: string;
  garage: string;
  virtual_tour: string;
  fireplace: string;
  open_house: string;
  exclusive: string;
}

export const DEFAULT_FILTERS: SearchFilters = {
  city: '',
  neighborhood: '',
  address: '',
  street: '',
  min_price: '',
  max_price: '',
  beds: '',
  baths: '',
  property_type: [],
  status: ['Active'],
  school_grade: '',
  price_reduced: '',
  new_listing_days: '',
  sort: 'newest',
  page: 1,
  sqft_min: '',
  sqft_max: '',
  lot_size_min: '',
  lot_size_max: '',
  year_built_min: '',
  year_built_max: '',
  max_dom: '',
  garage: '',
  virtual_tour: '',
  fireplace: '',
  open_house: '',
  exclusive: '',
};

/**
 * Create a new filter state, optionally merging partial overrides onto defaults.
 */
export function createFilterState(initial?: Partial<SearchFilters>): SearchFilters {
  const base = { ...DEFAULT_FILTERS };
  if (!initial) return base;

  return {
    ...base,
    ...initial,
    property_type: initial.property_type ? [...initial.property_type] : [...base.property_type],
    status: initial.status ? [...initial.status] : [...base.status],
  };
}

/** Keys that hold arrays and need comma-join/split for URL params */
const ARRAY_KEYS: (keyof SearchFilters)[] = ['property_type', 'status'];

/**
 * Serialize filters → URLSearchParams (for API calls and browser URLs).
 * V2 uses 'paged' instead of 'page' for URL pagination param.
 */
export function filtersToParams(filters: SearchFilters): URLSearchParams {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (key === 'page') {
      if ((value as number) > 1) params.set('paged', String(value));
      continue;
    }
    if (key === 'sort') {
      if (value && value !== 'newest') params.set('sort', value as string);
      continue;
    }
    if (ARRAY_KEYS.includes(key as keyof SearchFilters)) {
      const arr = value as string[];
      if (key === 'status' && arr.length === 1 && arr[0] === 'Active') continue;
      if (arr.length) params.set(key, arr.join(','));
      continue;
    }
    if (value !== '' && value !== null && value !== undefined) {
      params.set(key, String(value));
    }
  }

  return params;
}

/**
 * Deserialize URLSearchParams → SearchFilters.
 * V2 uses 'paged' for pagination param.
 */
export function filtersFromParams(params: URLSearchParams): SearchFilters {
  const f = createFilterState();

  f.city = params.get('city') || '';
  f.neighborhood = params.get('neighborhood') || '';
  f.address = params.get('address') || '';
  f.street = params.get('street') || '';
  f.min_price = params.get('min_price') || '';
  f.max_price = params.get('max_price') || '';
  f.beds = params.get('beds') || '';
  f.baths = params.get('baths') || '';
  f.property_type = params.get('property_type')?.split(',').filter(Boolean) || [];
  f.status = params.get('status')?.split(',').filter(Boolean) || ['Active'];
  f.school_grade = params.get('school_grade') || '';
  f.price_reduced = params.get('price_reduced') || '';
  f.new_listing_days = params.get('new_listing_days') || '';
  f.sort = params.get('sort') || 'newest';
  f.page = parseInt(params.get('paged') || '1', 10);
  f.sqft_min = params.get('sqft_min') || '';
  f.sqft_max = params.get('sqft_max') || '';
  f.lot_size_min = params.get('lot_size_min') || '';
  f.lot_size_max = params.get('lot_size_max') || '';
  f.year_built_min = params.get('year_built_min') || '';
  f.year_built_max = params.get('year_built_max') || '';
  f.max_dom = params.get('max_dom') || '';
  f.garage = params.get('garage') || '';
  f.virtual_tour = params.get('virtual_tour') || '';
  f.fireplace = params.get('fireplace') || '';
  f.open_house = params.get('open_house') || '';
  f.exclusive = params.get('exclusive') || '';

  return f;
}

/**
 * Build a full URL string from a base URL, filters, and optional page override.
 */
export function filtersToUrl(baseUrl: string, filters: SearchFilters, page?: number): string {
  const f = page !== undefined ? { ...filters, page } : filters;
  const qs = filtersToParams(f).toString();
  return qs ? `${baseUrl}?${qs}` : baseUrl;
}

/**
 * Toggle a value in an array — returns a NEW array.
 */
export function toggleArrayValue(arr: string[], value: string): string[] {
  const idx = arr.indexOf(value);
  if (idx >= 0) {
    return [...arr.slice(0, idx), ...arr.slice(idx + 1)];
  }
  return [...arr, value];
}

// ─── Chips ────────────────────────────────────────────────

export interface FilterChip {
  key: keyof SearchFilters;
  label: string;
  value?: string;
}

export function getActiveChips(filters: SearchFilters): FilterChip[] {
  const chips: FilterChip[] = [];

  if (filters.city) chips.push({ key: 'city', label: filters.city });
  if (filters.neighborhood) chips.push({ key: 'neighborhood', label: filters.neighborhood });
  if (filters.address) chips.push({ key: 'address', label: filters.address });
  if (filters.street) chips.push({ key: 'street', label: filters.street });
  if (filters.min_price || filters.max_price) {
    chips.push({ key: 'min_price', label: formatPriceRange(filters.min_price, filters.max_price) });
  }
  if (filters.beds) chips.push({ key: 'beds', label: `${filters.beds}+ Beds` });
  if (filters.baths) chips.push({ key: 'baths', label: `${filters.baths}+ Baths` });

  for (const t of filters.property_type) {
    chips.push({ key: 'property_type', label: t, value: t });
  }

  if (!(filters.status.length === 1 && filters.status[0] === 'Active')) {
    for (const s of filters.status) {
      chips.push({ key: 'status', label: s, value: s });
    }
  }

  if (filters.school_grade) chips.push({ key: 'school_grade', label: `Schools ${filters.school_grade}+` });
  if (filters.price_reduced === '1') chips.push({ key: 'price_reduced', label: 'Price Reduced' });
  if (filters.new_listing_days) chips.push({ key: 'new_listing_days', label: 'New This Week' });

  if (filters.sqft_min || filters.sqft_max) {
    const min = filters.sqft_min ? `${Number(filters.sqft_min).toLocaleString()}` : '0';
    const max = filters.sqft_max ? `${Number(filters.sqft_max).toLocaleString()}` : 'Any';
    chips.push({ key: 'sqft_min', label: `${min}–${max} sqft` });
  }
  if (filters.lot_size_min || filters.lot_size_max) {
    chips.push({ key: 'lot_size_min', label: 'Lot Size' });
  }
  if (filters.year_built_min || filters.year_built_max) {
    chips.push({ key: 'year_built_min', label: `Built ${filters.year_built_min || '?'}–${filters.year_built_max || '?'}` });
  }
  if (filters.max_dom) chips.push({ key: 'max_dom', label: `≤${filters.max_dom} days` });
  if (filters.garage) chips.push({ key: 'garage', label: `${filters.garage}+ Garage` });
  if (filters.virtual_tour === '1') chips.push({ key: 'virtual_tour', label: 'Virtual Tour' });
  if (filters.fireplace === '1') chips.push({ key: 'fireplace', label: 'Fireplace' });
  if (filters.open_house === '1') chips.push({ key: 'open_house', label: 'Open House' });
  if (filters.exclusive === '1') chips.push({ key: 'exclusive', label: 'Exclusive' });

  return chips;
}

export function removeChip(filters: SearchFilters, chip: FilterChip): SearchFilters {
  const f = { ...filters, property_type: [...filters.property_type], status: [...filters.status] };

  switch (chip.key) {
    case 'city': f.city = ''; break;
    case 'neighborhood': f.neighborhood = ''; break;
    case 'address': f.address = ''; break;
    case 'street': f.street = ''; break;
    case 'min_price': f.min_price = ''; f.max_price = ''; break;
    case 'beds': f.beds = ''; break;
    case 'baths': f.baths = ''; break;
    case 'property_type':
      f.property_type = chip.value ? f.property_type.filter(t => t !== chip.value) : [];
      break;
    case 'status':
      f.status = chip.value ? f.status.filter(s => s !== chip.value) : ['Active'];
      if (f.status.length === 0) f.status = ['Active'];
      break;
    case 'school_grade': f.school_grade = ''; break;
    case 'price_reduced': f.price_reduced = ''; break;
    case 'new_listing_days': f.new_listing_days = ''; break;
    case 'sqft_min': f.sqft_min = ''; f.sqft_max = ''; break;
    case 'lot_size_min': f.lot_size_min = ''; f.lot_size_max = ''; break;
    case 'year_built_min': f.year_built_min = ''; f.year_built_max = ''; break;
    case 'max_dom': f.max_dom = ''; break;
    case 'garage': f.garage = ''; break;
    case 'virtual_tour': f.virtual_tour = ''; break;
    case 'fireplace': f.fireplace = ''; break;
    case 'open_house': f.open_house = ''; break;
    case 'exclusive': f.exclusive = ''; break;
  }

  return f;
}

export function hasActiveFilters(filters: SearchFilters): boolean {
  return getActiveChips(filters).length > 0;
}

/**
 * Translate user-facing filter params → API param names.
 * Used by map search which calls the REST API directly from JS.
 */
export function filtersToApiParams(filters: SearchFilters): URLSearchParams {
  const params = filtersToParams(filters);

  const renames: Record<string, string> = {
    property_type: 'property_sub_type',
    street: 'street_name',
    garage: 'garage_spaces_min',
    virtual_tour: 'has_virtual_tour',
    fireplace: 'has_fireplace',
    open_house: 'open_house_only',
    exclusive: 'exclusive_only',
  };

  for (const [from, to] of Object.entries(renames)) {
    const val = params.get(from);
    if (val) {
      params.set(to, val);
      params.delete(from);
    }
  }

  // Sort value mapping
  if (params.get('sort') === 'newest' || !params.has('sort')) {
    params.set('sort', 'list_date_desc');
  }

  return params;
}

export function formatPriceRange(min: string, max: string): string {
  const fmt = (v: string) => {
    const n = Number(v);
    if (!n) return '';
    if (n >= 1_000_000) return `$${(n / 1_000_000).toFixed(1)}M`;
    if (n >= 1_000) return `$${Math.round(n / 1_000)}K`;
    return `$${n}`;
  };

  const fMin = fmt(min);
  const fMax = fmt(max);

  if (fMin && fMax) return `${fMin} – ${fMax}`;
  if (fMin) return `${fMin}+`;
  if (fMax) return `Up to ${fMax}`;
  return '';
}
