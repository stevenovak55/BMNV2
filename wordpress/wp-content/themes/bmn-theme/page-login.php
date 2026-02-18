<?php
/**
 * Template Name: Login
 *
 * Login page using Alpine.js auth component.
 * Calls POST /bmn/v1/auth/login.
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
        'heading' => 'Welcome Back',
        'mode'    => 'login',
    ));
    ?>

            <!-- Login Form -->
            <div x-data="authForm('login')">
                <!-- Error Message -->
                <div x-show="error" x-cloak
                     class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg"
                     x-text="error"></div>

                <!-- Success Message (for forgot password) -->
                <div x-show="success" x-cloak
                     class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg"
                     x-text="success"></div>

                <form @submit.prevent="submit()" class="space-y-4" x-show="mode !== 'forgot'">
                    <div>
                        <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="login-email" x-model="email" required
                               class="input-field" placeholder="you@example.com"
                               :class="fieldErrors.email && 'border-red-300 focus:border-red-500 focus:ring-red-500'">
                        <p x-show="fieldErrors.email" x-text="fieldErrors.email" class="mt-1 text-xs text-red-600"></p>
                    </div>

                    <div>
                        <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" id="login-password" x-model="password" required
                               class="input-field" placeholder="Enter your password"
                               :class="fieldErrors.password && 'border-red-300 focus:border-red-500 focus:ring-red-500'">
                        <p x-show="fieldErrors.password" x-text="fieldErrors.password" class="mt-1 text-xs text-red-600"></p>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click="mode = 'forgot'; clearErrors()"
                                class="text-sm text-navy-700 hover:text-navy-800 font-medium">
                            Forgot password?
                        </button>
                    </div>

                    <button type="submit" class="w-full btn-primary !py-3" :disabled="loading">
                        <span x-show="!loading">Log In</span>
                        <span x-show="loading" x-cloak>Logging in...</span>
                    </button>
                </form>

                <!-- Forgot Password Form -->
                <form @submit.prevent="submit()" class="space-y-4" x-show="mode === 'forgot'" x-cloak>
                    <p class="text-sm text-gray-600 mb-4">Enter your email and we'll send you a reset link.</p>

                    <div>
                        <label for="forgot-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="forgot-email" x-model="email" required
                               class="input-field" placeholder="you@example.com">
                    </div>

                    <button type="submit" class="w-full btn-primary !py-3" :disabled="loading">
                        <span x-show="!loading">Send Reset Link</span>
                        <span x-show="loading" x-cloak>Sending...</span>
                    </button>

                    <button type="button" @click="mode = 'login'; clearErrors()"
                            class="w-full text-sm text-gray-600 hover:text-navy-700 font-medium py-2">
                        Back to login
                    </button>
                </form>
            </div>

            <!-- Bottom Link -->
            <p class="mt-6 text-center text-sm text-gray-600">
                Don't have an account?
                <a href="<?php echo esc_url(home_url('/signup/')); ?>" class="font-medium text-navy-700 hover:text-navy-800">
                    Sign up
                </a>
            </p>
        </div>
    </div>
</div>
</main>

<?php get_footer(); ?>
