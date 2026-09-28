export default function orderDashboard() {
    return {
        products: [],
        lowStockProducts: [],
        lowStockThreshold: null,

        customer: {
            name: '',
            email: '',
        },

        items: [
            {
                product_id: '',
                quantity: 1,
            },
        ],

        historyEmail: '',
        orderHistory: null,

        orderResult: null,
        orderError: '',
        historyError: '',

        loadingProducts: false,
        submittingOrder: false,
        loadingHistory: false,
        loadingLowStock: false,

        async init() {
            await Promise.all([
                this.loadProducts(),
                this.loadLowStock(),
            ]);
        },

        async loadProducts() {
            this.loadingProducts = true;

            try {
                const response = await fetch('/api/products');
                const data = await response.json();

                if (!response.ok) {
                    throw new Error('Unable to load products.');
                }

                this.products = data.products;
            } catch (error) {
                this.orderError = error.message;
            } finally {
                this.loadingProducts = false;
            }
        },

        async loadLowStock() {
            this.loadingLowStock = true;

            try {
                const response = await fetch('/api/products/low-stock');
                const data = await response.json();

                if (!response.ok) {
                    throw new Error('Unable to load low-stock products.');
                }

                this.lowStockThreshold = data.threshold;
                this.lowStockProducts = data.products;
            } catch (error) {
                console.error(error);
            } finally {
                this.loadingLowStock = false;
            }
        },

        addItem() {
            this.items.push({
                product_id: '',
                quantity: 1,
            });
        },

        removeItem(index) {
            if (this.items.length === 1) {
                return;
            }

            this.items.splice(index, 1);
        },

        async submitOrder() {
            this.orderError = '';
            this.orderResult = null;
            this.submittingOrder = true;

            try {
                const response = await fetch('/api/orders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        customer: {
                            name: this.customer.name,
                            email: this.customer.email,
                        },
                        items: this.items.map(item => ({
                            product_id: Number(item.product_id),
                            quantity: Number(item.quantity),
                        })),
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(this.extractError(data));
                }

                this.orderResult = data;

                this.items = [
                    {
                        product_id: '',
                        quantity: 1,
                    },
                ];

                await Promise.all([
                    this.loadProducts(),
                    this.loadLowStock(),
                ]);
            } catch (error) {
                this.orderError = error.message;
            } finally {
                this.submittingOrder = false;
            }
        },

        async loadHistory() {
            this.historyError = '';
            this.orderHistory = null;
            this.loadingHistory = true;

            try {
                const params = new URLSearchParams({
                    email: this.historyEmail,
                });

                const response = await fetch(
                    `/api/customers/orders?${params.toString()}`
                );

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(this.extractError(data));
                }

                this.orderHistory = data;
            } catch (error) {
                this.historyError = error.message;
            } finally {
                this.loadingHistory = false;
            }
        },

        extractError(data) {
            if (data.errors) {
                return Object.values(data.errors)
                    .flat()
                    .join(' ');
            }

            return data.message || 'Something went wrong.';
        },

        productLabel(product) {
            return `${product.name} (${product.code}) — Stock: ${product.stock_quantity}`;
        },

        money(value) {
            return Number(value).toFixed(2);
        },
    };
}