<?php
/**
 * Nearby Schools
 *
 * Client-side fetch via Alpine.js to avoid blocking server-side render.
 * Fetches from /bmn/v1/schools/nearby?lat=X&lng=Y
 * Pitfall #11: No X-WP-Nonce header on public endpoints.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$lat = $args['lat'] ?? '';
$lng = $args['lng'] ?? '';

if (empty($lat) || empty($lng)) {
    return;
}
?>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6"
     x-data="{
         schools: [],
         district: null,
         loading: true,
         error: false,
         async init() {
             try {
                 const url = bmnPageData.schoolsApiUrl + '?lat=<?php echo esc_js($lat); ?>&lng=<?php echo esc_js($lng); ?>&radius=5';
                 const res = await fetch(url);
                 if (!res.ok) throw new Error('Failed to load');
                 const json = await res.json();
                 // V2 format: {success: true, data: [{name, level, ranking: {letter_grade}, distance}]}
                 const items = json.data || [];
                 // Normalize: flatten ranking.letter_grade into grade
                 this.schools = items.map(s => ({
                     ...s,
                     grade: s.ranking?.letter_grade || s.grade || s.letter_grade || 'N/A',
                     distance: s.distance ? s.distance.toFixed(1) : ''
                 }));
                 // Extract district from first school
                 if (this.schools.length > 0 && this.schools[0].district) {
                     this.district = { name: this.schools[0].district };
                 }
             } catch (e) {
                 this.error = true;
             } finally {
                 this.loading = false;
             }
         },
         groupByLevel(level) {
             return this.schools.filter(s => s.level === level);
         },
         gradeColor(grade) {
             if (!grade || grade === 'N/A') return 'bg-gray-100 text-gray-600';
             if (grade.startsWith('A')) return 'bg-green-100 text-green-700';
             if (grade.startsWith('B')) return 'bg-blue-100 text-blue-700';
             return 'bg-gray-100 text-gray-600';
         }
     }">

    <h2 class="text-lg font-semibold text-gray-900 mb-4">Nearby Schools</h2>

    <!-- Loading -->
    <div x-show="loading" class="flex items-center gap-2 py-4 text-sm text-gray-500">
        <svg class="animate-spin h-4 w-4 text-navy-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Loading school data...
    </div>

    <!-- Error state -->
    <div x-show="error && !loading" class="py-4 text-sm text-gray-500" x-cloak>
        School data is currently unavailable.
    </div>

    <!-- Empty state -->
    <div x-show="!loading && !error && schools.length === 0" class="py-4 text-sm text-gray-500" x-cloak>
        No nearby schools found.
    </div>

    <!-- Schools list -->
    <div x-show="!loading && !error && schools.length > 0" class="space-y-5" x-cloak>

        <!-- District info -->
        <template x-if="district">
            <div class="flex items-center gap-3 p-3 bg-navy-50 rounded-lg">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="gradeColor(district.grade)" x-text="district.grade || 'N/A'"></span>
                <div>
                    <p class="text-sm font-medium text-gray-900" x-text="district.name"></p>
                    <p class="text-xs text-gray-500">School District</p>
                </div>
            </div>
        </template>

        <!-- Elementary Schools -->
        <template x-if="groupByLevel('Elementary').length > 0">
            <div>
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Elementary</h3>
                <div class="space-y-2">
                    <template x-for="school in groupByLevel('Elementary')" :key="school.id || school.name">
                        <div class="flex items-center justify-between py-2 border-b border-gray-50">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="school.name"></p>
                                <p class="text-xs text-gray-500" x-text="school.distance ? school.distance + ' mi' : ''"></p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full flex-shrink-0 ml-2"
                                  :class="gradeColor(school.grade)"
                                  x-text="school.grade"></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Middle Schools -->
        <template x-if="groupByLevel('Middle').length > 0">
            <div>
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Middle</h3>
                <div class="space-y-2">
                    <template x-for="school in groupByLevel('Middle')" :key="school.id || school.name">
                        <div class="flex items-center justify-between py-2 border-b border-gray-50">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="school.name"></p>
                                <p class="text-xs text-gray-500" x-text="school.distance ? school.distance + ' mi' : ''"></p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full flex-shrink-0 ml-2"
                                  :class="gradeColor(school.grade)"
                                  x-text="school.grade"></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- High Schools -->
        <template x-if="groupByLevel('High').length > 0">
            <div>
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">High</h3>
                <div class="space-y-2">
                    <template x-for="school in groupByLevel('High')" :key="school.id || school.name">
                        <div class="flex items-center justify-between py-2 border-b border-gray-50">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="school.name"></p>
                                <p class="text-xs text-gray-500" x-text="school.distance ? school.distance + ' mi' : ''"></p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full flex-shrink-0 ml-2"
                                  :class="gradeColor(school.grade)"
                                  x-text="school.grade"></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>
