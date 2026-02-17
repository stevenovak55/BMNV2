/**
 * Mortgage Calculator Alpine.js Data Component
 *
 * Reactive calculator with sliders for price/down payment,
 * term buttons, rate input, and live payment breakdown.
 */

interface MortgageCalcOptions {
  defaultRate: number;
  defaultTaxRate: number;
  defaultInsurance: number;
}

export function mortgageCalcComponent(options: MortgageCalcOptions = {
  defaultRate: 6.5,
  defaultTaxRate: 1.2,
  defaultInsurance: 1200,
}) {
  return {
    homePrice: 500000,
    downPaymentPct: 20,
    loanTerm: 30,
    interestRate: options.defaultRate,
    taxRate: options.defaultTaxRate,
    annualInsurance: options.defaultInsurance,

    get downPayment(): number {
      return Math.round(this.homePrice * this.downPaymentPct / 100);
    },

    get loanAmount(): number {
      return this.homePrice - this.downPayment;
    },

    get monthlyPI(): number {
      const principal = this.loanAmount;
      const monthlyRate = this.interestRate / 100 / 12;
      const numPayments = this.loanTerm * 12;

      if (monthlyRate === 0) {
        return principal / numPayments;
      }

      return principal * (monthlyRate * Math.pow(1 + monthlyRate, numPayments))
        / (Math.pow(1 + monthlyRate, numPayments) - 1);
    },

    get monthlyTax(): number {
      return (this.homePrice * this.taxRate / 100) / 12;
    },

    get monthlyInsurance(): number {
      return this.annualInsurance / 12;
    },

    get showPMI(): boolean {
      return this.downPaymentPct < 20;
    },

    get monthlyPMI(): number {
      if (!this.showPMI) return 0;
      // PMI typically 0.5-1% of loan annually
      return (this.loanAmount * 0.007) / 12;
    },

    get totalMonthly(): number {
      return Math.round(this.monthlyPI + this.monthlyTax + this.monthlyInsurance + this.monthlyPMI);
    },

    formatCurrency(value: number): string {
      return '$' + Math.round(value).toLocaleString('en-US');
    },
  };
}
