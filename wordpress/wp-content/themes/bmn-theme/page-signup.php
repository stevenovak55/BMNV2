<?php
/**
 * Template Name: Sign Up
 *
 * Registration page using Alpine.js auth component.
 * Calls POST /bmn/v1/auth/register.
 *
 * @package bmn_theme
 * @version 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main" class="flex-1">
    <?php
    get_template_part('template-parts/auth/auth-layout', null, array(
        'heading' => 'Create Your Account',
        'mode'    => 'register',
    ));
    ?>

            <!-- Registration Form -->
            <div x-data="authForm('register')">
                <!-- Error Message -->
                <div x-show="error" x-cloak
                     class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg"
                     x-text="error"></div>

                <form @submit.prevent="submit()" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="signup-first" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" id="signup-first" x-model="firstName" required
                                   class="input-field" placeholder="First name"
                                   :class="fieldErrors.firstName && 'border-red-300 focus:border-red-500 focus:ring-red-500'">
                            <p x-show="fieldErrors.firstName" x-text="fieldErrors.firstName" class="mt-1 text-xs text-red-600"></p>
                        </div>
                        <div>
                            <label for="signup-last" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" id="signup-last" x-model="lastName" required
                                   class="input-field" placeholder="Last name"
                                   :class="fieldErrors.lastName && 'border-red-300 focus:border-red-500 focus:ring-red-500'">
                            <p x-show="fieldErrors.lastName" x-text="fieldErrors.lastName" class="mt-1 text-xs text-red-600"></p>
                        </div>
                    </div>

                    <div>
                        <label for="signup-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="signup-email" x-model="email" required
                               class="input-field" placeholder="you@example.com"
                               :class="fieldErrors.email && 'border-red-300 focus:border-red-500 focus:ring-red-500'">
                        <p x-show="fieldErrors.email" x-text="fieldErrors.email" class="mt-1 text-xs text-red-600"></p>
                    </div>

                    <div>
                        <label for="signup-password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" id="signup-password" x-model="password" required minlength="8"
                               class="input-field" placeholder="At least 8 characters"
                               :class="fieldErrors.password && 'border-red-300 focus:border-red-500 focus:ring-red-500'">
                        <p x-show="fieldErrors.password" x-text="fieldErrors.password" class="mt-1 text-xs text-red-600"></p>
                    </div>

                    <div>
                        <label for="signup-phone" class="block text-sm font-medium text-gray-700 mb-1">
                            Phone <span class="text-gray-400">(optional)</span>
                        </label>
                        <input type="tel" id="signup-phone" x-model="phone"
                               class="input-field" placeholder="(555) 123-4567">
                    </div>

                    <button type="submit" class="w-full btn-primary !py-3" :disabled="loading">
                        <span x-show="!loading">Create Account</span>
                        <span x-show="loading" x-cloak>Creating account...</span>
                    </button>
                </form>
            </div>

            <!-- Bottom Link -->
            <p class="mt-6 text-center text-sm text-gray-600">
                Already have an account?
                <a href="<?php echo esc_url(home_url('/login/')); ?>" class="font-medium text-navy-700 hover:text-navy-800">
                    Log in
                </a>
            </p>
        </div>
    </div>
</div>
</main>

<?php get_footer(); ?>
