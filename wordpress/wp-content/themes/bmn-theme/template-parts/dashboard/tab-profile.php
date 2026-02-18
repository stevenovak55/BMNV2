<?php
/**
 * Dashboard: Profile / Settings Tab
 *
 * Displays user info, logout, and delete account.
 * Data loaded via Alpine.js from REST API.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div x-show="activeTab === 'profile'">
    <!-- Loading -->
    <div x-show="loading && !profileLoaded" class="flex justify-center py-12">
        <svg class="animate-spin h-8 w-8 text-navy-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    <!-- Profile Card -->
    <div x-show="profileLoaded && profile" class="max-w-lg">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <!-- User Info -->
            <div class="flex items-center gap-4 mb-6">
                <div class="w-14 h-14 rounded-full bg-navy-100 flex items-center justify-center">
                    <span class="text-xl font-bold text-navy-700"
                          x-text="profile ? (profile.first_name || profile.display_name || 'U').charAt(0).toUpperCase() : 'U'"></span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"
                        x-text="profile ? (profile.display_name || `${profile.first_name} ${profile.last_name}`) : ''"></h3>
                    <p class="text-sm text-gray-500" x-text="profile?.email || ''"></p>
                </div>
            </div>

            <div class="space-y-4 border-t border-gray-100 pt-6">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Member since</span>
                    <span class="text-gray-900 font-medium" x-text="formatDate(profile?.created_at || '')"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Saved favorites</span>
                    <span class="text-gray-900 font-medium" x-text="favorites.length"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Saved searches</span>
                    <span class="text-gray-900 font-medium" x-text="savedSearches.length"></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-8 space-y-3">
                <button @click="logout()" class="w-full btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Log Out
                </button>

                <div>
                    <button @click="confirmAccountDelete = !confirmAccountDelete"
                            class="w-full text-sm text-gray-400 hover:text-red-500 transition-colors py-2">
                        Delete Account
                    </button>
                    <div x-show="confirmAccountDelete"
                         x-transition
                         class="mt-2 bg-red-50 rounded-lg p-4 text-center">
                        <p class="text-sm text-red-700 mb-3">This will permanently delete your account, favorites, and saved searches. This cannot be undone.</p>
                        <div class="flex gap-2 justify-center">
                            <button @click="deleteAccount()" class="btn-red text-sm !py-2 !px-4">
                                Yes, Delete My Account
                            </button>
                            <button @click="confirmAccountDelete = false" class="btn-secondary text-sm !py-2 !px-4">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
