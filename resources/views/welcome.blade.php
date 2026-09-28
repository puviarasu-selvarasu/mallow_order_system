<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Store Order & Inventory</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <main class="container" x-data="orderDashboard">

        <header class="page-header">
            <h1>Store Order & Inventory</h1>
            <p>Create orders, review customer history and monitor inventory.</p>
        </header>

        <section class="card">
            <h2>Create Order</h2>

            <form @submit.prevent="submitOrder">
                <div class="form-grid">
                    <label>
                        Customer Name
                        <input
                            type="text"
                            x-model="customer.name"
                            required
                        >
                    </label>

                    <label>
                        Customer Email
                        <input
                            type="email"
                            x-model="customer.email"
                            required
                        >
                    </label>
                </div>

                <h3>Products</h3>

                <template x-for="(item, index) in items" :key="index">
                    <div class="order-line">
                        <select x-model="item.product_id" required>
                            <option value="">Select product</option>

                            <template x-for="product in products" :key="product.id">
                                <option
                                    :value="product.id"
                                    :disabled="product.stock_quantity === 0"
                                    x-text="productLabel(product)"
                                ></option>
                            </template>
                        </select>

                        <input
                            type="number"
                            min="1"
                            x-model.number="item.quantity"
                            required
                        >

                        <button
                            type="button"
                            class="button-secondary"
                            @click="removeItem(index)"
                            :disabled="items.length === 1"
                        >
                            Remove
                        </button>
                    </div>
                </template>

                <div class="actions">
                    <button
                        type="button"
                        class="button-secondary"
                        @click="addItem"
                    >
                        Add Product
                    </button>

                    <button
                        type="submit"
                        :disabled="submittingOrder || loadingProducts"
                    >
                        <span x-show="!submittingOrder">Create Order</span>
                        <span x-show="submittingOrder">Creating...</span>
                    </button>
                </div>
            </form>

            <p
                class="message error"
                x-show="orderError"
                x-text="orderError"
            ></p>

            <div
                class="message success"
                x-show="orderResult"
            >
                <strong>Order created successfully.</strong>

                <p>
                    Order #
                    <span x-text="orderResult?.id"></span>
                </p>

                <p>
                    Subtotal:
                    ₹<span x-text="money(orderResult?.subtotal)"></span>
                </p>

                <p>
                    Tax:
                    ₹<span x-text="money(orderResult?.tax_amount)"></span>
                </p>

                <p>
                    Grand Total:
                    ₹<span x-text="money(orderResult?.grand_total)"></span>
                </p>
            </div>
        </section>

        <div class="dashboard-grid">

            <section class="card">
                <h2>Customer Order History</h2>

                <form @submit.prevent="loadHistory">
                    <label>
                        Customer Email
                        <input
                            type="email"
                            x-model="historyEmail"
                            required
                        >
                    </label>

                    <button
                        type="submit"
                        :disabled="loadingHistory"
                    >
                        <span x-show="!loadingHistory">Search</span>
                        <span x-show="loadingHistory">Loading...</span>
                    </button>
                </form>

                <p
                    class="message error"
                    x-show="historyError"
                    x-text="historyError"
                ></p>

                <div x-show="orderHistory">
                    <template x-if="orderHistory?.orders?.length === 0">
                        <p>No orders found for this customer.</p>
                    </template>

                    <template
                        x-for="order in orderHistory?.orders || []"
                        :key="order.id"
                    >
                        <article class="history-order">
                            <h3>
                                Order #
                                <span x-text="order.id"></span>
                            </h3>

                            <p>
                                Grand Total:
                                ₹<span x-text="money(order.grand_total)"></span>
                            </p>

                            <p>
                                Items:
                                <span x-text="order.items.length"></span>
                            </p>
                        </article>
                    </template>
                </div>
            </section>

            <section class="card">
                <h2>Low Stock Products</h2>

                <p>
                    Threshold:
                    <strong x-text="lowStockThreshold ?? '-'"></strong>
                </p>

                <p x-show="loadingLowStock">
                    Loading inventory...
                </p>

                <template x-if="!loadingLowStock && lowStockProducts.length === 0">
                    <p>No products are currently below the threshold.</p>
                </template>

                <div class="inventory-list">
                    <template
                        x-for="product in lowStockProducts"
                        :key="product.id"
                    >
                        <article class="inventory-item">
                            <div>
                                <strong x-text="product.name"></strong>
                                <small x-text="product.code"></small>
                            </div>

                            <span>
                                Stock:
                                <strong x-text="product.stock_quantity"></strong>
                            </span>
                        </article>
                    </template>
                </div>
            </section>

        </div>

    </main>
</body>
</html>