<?php
/**
 * Homepage: Mortgage Calculator
 *
 * Interactive Alpine.js calculator with sliders, term buttons, and live payment breakdown.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$default_rate = floatval(get_theme_mod('bne_default_mortgage_rate', 6.5));
$default_tax_rate = floatval(get_theme_mod('bne_default_property_tax_rate', 1.2));
$default_insurance = intval(get_theme_mod('bne_default_home_insurance', 1200));
?>

<section class="py-12 md:py-16 lg:py-20 bg-gray-50" aria-labelledby="mortgage-title">
    <div class="max-w-5xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="mortgage-title" class="section-title">Mortgage Calculator</h2>
            <p class="section-subtitle mx-auto">Estimate your monthly payment and see how much home you can afford.</p>
        </div>

        <div class="glass-card"
             x-data="mortgageCalc({
                 defaultRate: <?php echo esc_attr($default_rate); ?>,
                 defaultTaxRate: <?php echo esc_attr($default_tax_rate); ?>,
                 defaultInsurance: <?php echo esc_attr($default_insurance); ?>
             })">
            <div class="grid lg:grid-cols-2 gap-8">

                <!-- Inputs -->
                <div class="space-y-6">
                    <!-- Home Price -->
                    <div>
                        <label class="flex justify-between text-sm font-medium text-gray-700 mb-2">
                            <span>Home Price</span>
                            <span class="text-navy-700 font-semibold" x-text="formatCurrency(homePrice)"></span>
                        </label>
                        <input type="range" x-model.number="homePrice" min="50000" max="5000000" step="10000"
                               class="w-full accent-navy-700">
                        <div class="flex justify-between text-xs text-gray-400 mt-1">
                            <span>$50K</span><span>$5M</span>
                        </div>
                    </div>

                    <!-- Down Payment -->
                    <div>
                        <label class="flex justify-between text-sm font-medium text-gray-700 mb-2">
                            <span>Down Payment</span>
                            <span class="text-navy-700 font-semibold" x-text="formatCurrency(downPayment) + ' (' + downPaymentPct + '%)'"></span>
                        </label>
                        <input type="range" x-model.number="downPaymentPct" min="0" max="50" step="1"
                               class="w-full accent-navy-700">
                        <div class="flex justify-between text-xs text-gray-400 mt-1">
                            <span>0%</span><span>50%</span>
                        </div>
                    </div>

                    <!-- Loan Term -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Loan Term</label>
                        <div class="flex gap-2">
                            <?php foreach (array(15, 20, 30) as $term) : ?>
                                <button type="button"
                                        @click="loanTerm = <?php echo $term; ?>"
                                        :class="loanTerm === <?php echo $term; ?> ? 'bg-navy-700 text-white border-navy-700' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300'"
                                        class="flex-1 py-2 rounded-lg border-2 text-sm font-medium transition-colors">
                                    <?php echo $term; ?> years
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Interest Rate -->
                    <div>
                        <label class="flex justify-between text-sm font-medium text-gray-700 mb-2">
                            <span>Interest Rate</span>
                            <span class="text-navy-700 font-semibold" x-text="interestRate + '%'"></span>
                        </label>
                        <input type="range" x-model.number="interestRate" min="1" max="12" step="0.125"
                               class="w-full accent-navy-700">
                    </div>
                </div>

                <!-- Results -->
                <div class="flex flex-col justify-center">
                    <div class="text-center lg:text-left mb-6">
                        <p class="text-sm text-gray-500 uppercase tracking-wider font-medium">Estimated Monthly Payment</p>
                        <p class="text-4xl lg:text-5xl font-bold text-navy-700 mt-1" x-text="formatCurrency(totalMonthly)"></p>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Principal & Interest</span>
                            <span class="text-sm font-medium text-gray-900" x-text="formatCurrency(monthlyPI)"></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Property Tax</span>
                            <span class="text-sm font-medium text-gray-900" x-text="formatCurrency(monthlyTax)"></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Home Insurance</span>
                            <span class="text-sm font-medium text-gray-900" x-text="formatCurrency(monthlyInsurance)"></span>
                        </div>
                        <template x-if="showPMI">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-sm text-gray-600">PMI</span>
                                <span class="text-sm font-medium text-gray-900" x-text="formatCurrency(monthlyPMI)"></span>
                            </div>
                        </template>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-sm font-semibold text-gray-900">Loan Amount</span>
                            <span class="text-sm font-semibold text-gray-900" x-text="formatCurrency(loanAmount)"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
