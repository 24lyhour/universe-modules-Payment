<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import {
    ArrowLeft,
    Hash,
    User,
    Store,
    Clock,
    CheckCircle,
    XCircle,
    CreditCard,
    DollarSign,
    Wallet,
    Banknote,
    FileText,
    AlertTriangle,
    ExternalLink,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { TransactionShowProps } from '@payment/types';

defineOptions({
    layout: (h: (type: unknown, props: unknown, children: unknown) => VNode, page: VNode) =>
        h(AppLayout, { breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Transactions', href: '/dashboard/payment-transactions' },
            { title: 'Transaction Detail', href: '#' },
        ]}, () => page),
});

const props = defineProps<TransactionShowProps>();

const handleBack = () => router.visit('/dashboard/payment-transactions');

const handleViewOrder = () => {
    if (props.transaction.order?.uuid) {
        router.visit(`/dashboard/orders/${props.transaction.order.uuid}`);
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
};

const formatDateTime = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit', hour12: true,
    });
};

const statusConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: typeof Clock }> = {
    pending: { label: 'Pending', variant: 'outline', icon: Clock },
    processing: { label: 'Processing', variant: 'secondary', icon: Clock },
    completed: { label: 'Completed', variant: 'default', icon: CheckCircle },
    failed: { label: 'Failed', variant: 'destructive', icon: XCircle },
};

const getPaymentMethodLabel = (method: string | null) => {
    const labels: Record<string, string> = {
        wallet: 'Wallet', cash: 'Cash on Delivery',
        aba_payway: 'ABA PayWay', credit_card: 'Credit Card',
    };
    return labels[method ?? ''] || method || 'Unknown';
};

const getPaymentMethodIcon = (method: string | null) => {
    if (method === 'wallet') return Wallet;
    if (method === 'cash') return Banknote;
    return CreditCard;
};
</script>

<template>
    <Head :title="`Transaction ${transaction.transaction_number}`" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <Button variant="ghost" @click="handleBack">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Transactions
            </Button>
            <Button v-if="transaction.order" variant="outline" @click="handleViewOrder">
                <ExternalLink class="mr-2 h-4 w-4" />
                View Order
            </Button>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Main Info -->
            <div class="lg:col-span-2 space-y-4">
                <!-- Transaction Info -->
                <Card>
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div>
                                <CardTitle class="text-2xl font-mono">{{ transaction.transaction_number }}</CardTitle>
                                <p class="text-sm text-muted-foreground mt-1">
                                    {{ transaction.type === 'refund' ? 'Refund Transaction' : 'Payment Transaction' }}
                                </p>
                            </div>
                            <Badge
                                :variant="statusConfig[transaction.status]?.variant || 'outline'"
                                class="capitalize text-sm px-3 py-1"
                            >
                                <component
                                    :is="statusConfig[transaction.status]?.icon || Clock"
                                    class="h-3.5 w-3.5 mr-1.5"
                                />
                                {{ statusConfig[transaction.status]?.label || transaction.status }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Amount -->
                        <div class="flex items-center justify-between p-4 rounded-lg bg-muted/50">
                            <span class="text-muted-foreground">Amount</span>
                            <span class="text-3xl font-bold text-primary tabular-nums">
                                {{ formatCurrency(transaction.amount) }}
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Fee</span>
                                <span>{{ formatCurrency(transaction.fee) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Net Amount</span>
                                <span class="font-medium">{{ formatCurrency(transaction.net_amount) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Currency</span>
                                <span>{{ transaction.currency }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Type</span>
                                <Badge :variant="transaction.type === 'refund' ? 'destructive' : 'secondary'" class="capitalize">
                                    {{ transaction.type }}
                                </Badge>
                            </div>
                        </div>

                        <Separator />

                        <!-- Payment Method -->
                        <div>
                            <h4 class="text-sm font-medium text-muted-foreground mb-3">Payment Method</h4>
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                    <component :is="getPaymentMethodIcon(transaction.payment_method)" class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p class="font-medium">{{ getPaymentMethodLabel(transaction.payment_method) }}</p>
                                    <p v-if="transaction.gateway_transaction_id" class="text-xs text-muted-foreground font-mono">
                                        Gateway ID: {{ transaction.gateway_transaction_id }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Failure Reason -->
                        <div v-if="transaction.failure_reason">
                            <Separator />
                            <div class="flex items-start gap-3 mt-4 p-3 rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800">
                                <AlertTriangle class="h-5 w-5 text-red-600 mt-0.5 shrink-0" />
                                <div>
                                    <p class="font-medium text-red-700 dark:text-red-300">Failure Reason</p>
                                    <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ transaction.failure_reason }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="transaction.notes">
                            <Separator />
                            <div class="mt-4">
                                <h4 class="text-sm font-medium text-muted-foreground mb-2">Notes</h4>
                                <p class="text-sm">{{ transaction.notes }}</p>
                            </div>
                        </div>

                        <!-- Gateway Response (collapsible) -->
                        <div v-if="transaction.gateway_response">
                            <Separator />
                            <div class="mt-4">
                                <h4 class="text-sm font-medium text-muted-foreground mb-2">Gateway Response</h4>
                                <pre class="text-xs bg-muted p-3 rounded-lg overflow-auto max-h-48">{{ JSON.stringify(transaction.gateway_response, null, 2) }}</pre>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <!-- Order Info -->
                <Card v-if="transaction.order">
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Hash class="h-4 w-4" />
                            Order
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Order #</span>
                            <span class="font-mono font-medium">{{ transaction.order.order_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Total</span>
                            <span class="font-medium">{{ formatCurrency(transaction.order.total_amount) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Status</span>
                            <Badge variant="outline" class="capitalize">{{ transaction.order.status }}</Badge>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Payment</span>
                            <Badge variant="secondary" class="capitalize">{{ transaction.order.payment_status }}</Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Customer Info -->
                <Card v-if="transaction.customer">
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <User class="h-4 w-4" />
                            Customer
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Name</span>
                            <span class="font-medium">{{ transaction.customer.name }}</span>
                        </div>
                        <div v-if="transaction.customer.email" class="flex justify-between">
                            <span class="text-muted-foreground">Email</span>
                            <span class="text-sm">{{ transaction.customer.email }}</span>
                        </div>
                        <div v-if="transaction.customer.phone" class="flex justify-between">
                            <span class="text-muted-foreground">Phone</span>
                            <span class="text-sm">{{ transaction.customer.phone }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Outlet Info -->
                <Card v-if="transaction.outlet">
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Store class="h-4 w-4" />
                            Outlet
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <span class="font-medium">{{ transaction.outlet.name }}</span>
                    </CardContent>
                </Card>

                <!-- Timestamps -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Clock class="h-4 w-4" />
                            Timeline
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Created</span>
                            <span class="text-sm">{{ formatDateTime(transaction.created_at) }}</span>
                        </div>
                        <div v-if="transaction.processed_at" class="flex justify-between">
                            <span class="text-muted-foreground">Processed</span>
                            <span class="text-sm">{{ formatDateTime(transaction.processed_at) }}</span>
                        </div>
                        <div v-if="transaction.failed_at" class="flex justify-between">
                            <span class="text-muted-foreground">Failed</span>
                            <span class="text-sm text-red-600">{{ formatDateTime(transaction.failed_at) }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
