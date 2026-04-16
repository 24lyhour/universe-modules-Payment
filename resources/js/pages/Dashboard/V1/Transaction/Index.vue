<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, type VNode } from 'vue';
import {
    DollarSign,
    Clock,
    CheckCircle,
    XCircle,
    Loader2,
    Search,
    Eye,
    X,
    Hash,
    User,
    Store,
    CreditCard,
    Banknote,
    Wallet,
    ArrowDownRight,
    ArrowUpRight,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { SidebarFilter, StatsCard, Pagination, CardWidget, type CardAction, type FilterItem } from '@/components/shared';
import { formatRelativeTime, isRecentDate } from '@/composables/useRelativeTime';
import type { TransactionIndexProps, TransactionItem } from '@payment/types';

defineOptions({
    layout: (h: (type: unknown, props: unknown, children: unknown) => VNode, page: VNode) =>
        h(AppLayout, { breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Transactions', href: '/dashboard/payment-transactions' },
        ]}, () => page),
});

const props = defineProps<TransactionIndexProps>();

// State
const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const paymentMethodFilter = ref(props.filters.payment_method || 'all');
const outletFilter = ref(props.filters.outlet_id?.toString() || 'all');

// Status tabs
const statusTabs = computed<FilterItem[]>(() => [
    { key: 'all', label: 'All', count: props.stats.total, icon: DollarSign, color: 'text-blue-600', bgColor: 'bg-blue-100' },
    { key: 'pending', label: 'Pending', count: props.stats.pending ?? 0, icon: Clock, color: 'text-yellow-600', bgColor: 'bg-yellow-100' },
    { key: 'processing', label: 'Processing', count: props.stats.processing ?? 0, icon: Loader2, color: 'text-sky-600', bgColor: 'bg-sky-100' },
    { key: 'completed', label: 'Completed', count: props.stats.completed ?? 0, icon: CheckCircle, color: 'text-emerald-600', bgColor: 'bg-emerald-100' },
    { key: 'failed', label: 'Failed', count: props.stats.failed ?? 0, icon: XCircle, color: 'text-red-600', bgColor: 'bg-red-100' },
]);

const handleStatusTabClick = (status: string) => {
    router.get('/dashboard/payment-transactions', {
        search: searchQuery.value || undefined,
        status: status !== 'all' ? status : undefined,
        payment_method: paymentMethodFilter.value !== 'all' ? paymentMethodFilter.value : undefined,
        outlet_id: outletFilter.value !== 'all' ? outletFilter.value : undefined,
        page: 1,
    }, { preserveState: true, preserveScroll: true });
};

const pagination = computed(() => ({
    current_page: props.transactionItems.meta.current_page,
    last_page: props.transactionItems.meta.last_page,
    per_page: props.transactionItems.meta.per_page,
    total: props.transactionItems.meta.total,
}));

const handleView = (item: TransactionItem) => router.visit(`/dashboard/payment-transactions/${item.uuid}`);

const applyFilters = (overrides: { page?: number; per_page?: number } = {}) => {
    router.get('/dashboard/payment-transactions', {
        search: searchQuery.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        payment_method: paymentMethodFilter.value !== 'all' ? paymentMethodFilter.value : undefined,
        outlet_id: outletFilter.value !== 'all' ? outletFilter.value : undefined,
        ...overrides,
    }, { preserveState: true });
};

const handlePageChange = (page: number) => applyFilters({ page, per_page: pagination.value.per_page });
const handlePerPageChange = (perPage: number) => applyFilters({ page: 1, per_page: perPage });
const handleSearch = (search: string) => { searchQuery.value = search; applyFilters({ page: 1 }); };

const handlePaymentMethodFilter = (value: string | number | boolean | bigint | Record<string, unknown> | null | undefined) => {
    paymentMethodFilter.value = String(value || 'all');
    applyFilters({ page: 1 });
};

const handleOutletFilter = (value: string | number | boolean | bigint | Record<string, unknown> | null | undefined) => {
    outletFilter.value = String(value || 'all');
    applyFilters({ page: 1 });
};

const handleRowClick = (item: TransactionItem) => router.visit(`/dashboard/payment-transactions/${item.uuid}`);

const hasActiveFilters = computed(() => {
    return !!(searchQuery.value || statusFilter.value !== 'all' || paymentMethodFilter.value !== 'all' || outletFilter.value !== 'all');
});

const handleClearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = 'all';
    paymentMethodFilter.value = 'all';
    outletFilter.value = 'all';
    router.get('/dashboard/payment-transactions', {}, { preserveState: true, preserveScroll: true });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatFullDateTime = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit', hour12: true,
    });
};

const statusConfig: Record<string, { icon: typeof Clock; bgClass: string }> = {
    pending: { icon: Clock, bgClass: 'bg-yellow-500 text-white' },
    processing: { icon: Loader2, bgClass: 'bg-sky-500 text-white' },
    completed: { icon: CheckCircle, bgClass: 'bg-emerald-500 text-white' },
    failed: { icon: XCircle, bgClass: 'bg-red-500 text-white' },
};

const getStatusBgClass = (status: string) => statusConfig[status]?.bgClass || 'bg-gray-500 text-white';
const getStatusIcon = (status: string) => statusConfig[status]?.icon || Clock;

const getPaymentMethodIcon = (method: string | null) => {
    if (!method) return DollarSign;
    if (method === 'wallet') return Wallet;
    if (method === 'cash') return Banknote;
    if (method === 'aba_payway') return CreditCard;
    return CreditCard;
};

const getPaymentMethodLabel = (method: string | null) => {
    if (!method) return 'Unknown';
    const labels: Record<string, string> = {
        wallet: 'Wallet',
        cash: 'Cash',
        aba_payway: 'ABA PayWay',
        credit_card: 'Credit Card',
    };
    return labels[method] || method;
};

const getCardActions = (txn: TransactionItem): CardAction[] => [
    { label: 'View Details', icon: Eye, onClick: () => handleView(txn) },
];
</script>

<template>
    <Head title="Transactions" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Transactions</h1>
                <p class="text-muted-foreground">View and track all payment transactions</p>
            </div>
        </div>

        <!-- Stats Cards Row -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                title="Total Transactions"
                :value="stats.total"
                :icon="DollarSign"
                icon-color="text-blue-500"
            />
            <StatsCard
                title="Completed"
                :value="stats.completed ?? 0"
                :icon="CheckCircle"
                icon-color="text-green-500"
                value-color="text-green-600"
            />
            <StatsCard
                title="Total Revenue"
                :value="formatCurrency(stats.total_revenue)"
                :icon="ArrowUpRight"
                icon-color="text-emerald-500"
                value-color="text-emerald-600"
            />
            <StatsCard
                title="Total Refunded"
                :value="formatCurrency(stats.total_refunded)"
                :icon="ArrowDownRight"
                icon-color="text-red-500"
                value-color="text-red-600"
            />
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6 items-start">
            <!-- Left Sidebar: Status Filters -->
            <SidebarFilter
                v-model="statusFilter"
                title="Filter by Status"
                :items="statusTabs"
                @update:model-value="handleStatusTabClick"
            />

            <!-- Right Content -->
            <div class="space-y-4">
                <!-- Search & Filters Bar -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-[200px]">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search by transaction # or order #..."
                            class="pl-9"
                            @input="handleSearch(searchQuery)"
                        />
                    </div>

                    <!-- Payment Method Filter -->
                    <Select :model-value="paymentMethodFilter" @update:model-value="handlePaymentMethodFilter">
                        <SelectTrigger class="w-[150px]">
                            <SelectValue placeholder="All Methods" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Methods</SelectItem>
                            <SelectItem
                                v-for="method in props.paymentMethods"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Outlet Filter -->
                    <Select v-if="props.outlets && props.outlets.length > 0" :model-value="outletFilter" @update:model-value="handleOutletFilter">
                        <SelectTrigger class="w-[160px]">
                            <SelectValue placeholder="All Outlets" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Outlets</SelectItem>
                            <SelectItem
                                v-for="outlet in props.outlets"
                                :key="outlet.id"
                                :value="outlet.id.toString()"
                            >
                                {{ outlet.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        @click="handleClearFilters"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        <X class="mr-1 h-4 w-4" />
                        Clear
                    </Button>
                </div>

                <!-- Transaction Cards Grid -->
                <div v-if="props.transactionItems.data.length > 0" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <CardWidget
                        v-for="txn in props.transactionItems.data"
                        :key="txn.id"
                        :actions="getCardActions(txn)"
                        @click="handleRowClick(txn)"
                    >
                        <template #header-icon>
                            <Hash class="h-4 w-4 text-muted-foreground" />
                        </template>

                        <template #header-title>
                            <span class="font-mono font-bold text-primary">{{ txn.transaction_number }}</span>
                        </template>

                        <template #header-badge>
                            <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold capitalize', getStatusBgClass(txn.status)]">
                                <component :is="getStatusIcon(txn.status)" class="h-3 w-3" />
                                {{ txn.status }}
                            </span>
                        </template>

                        <template #body>
                            <div v-if="txn.customer" class="flex items-center gap-2 text-sm">
                                <User class="h-4 w-4 text-muted-foreground shrink-0" />
                                <span class="truncate font-medium">{{ txn.customer.name }}</span>
                            </div>
                            <div v-if="txn.outlet" class="flex items-center gap-2 text-sm">
                                <Store class="h-4 w-4 text-muted-foreground shrink-0" />
                                <span class="truncate text-muted-foreground">{{ txn.outlet.name }}</span>
                            </div>
                            <div v-if="txn.order" class="flex items-center gap-2 text-sm">
                                <Hash class="h-4 w-4 text-muted-foreground shrink-0" />
                                <span class="truncate text-muted-foreground">{{ txn.order.order_number }}</span>
                            </div>
                        </template>

                        <template #footer-left>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-lg tabular-nums">{{ formatCurrency(txn.amount) }}</span>
                                <Badge v-if="txn.type === 'refund'" variant="destructive" class="text-xs">Refund</Badge>
                            </div>
                        </template>

                        <template #footer-right>
                            <Badge variant="outline" class="text-xs">
                                <component :is="getPaymentMethodIcon(txn.payment_method)" class="h-3 w-3 mr-1" />
                                {{ getPaymentMethodLabel(txn.payment_method) }}
                            </Badge>
                        </template>

                        <template #sub-footer>
                            <div class="flex items-center gap-1.5 mt-2 text-xs text-muted-foreground">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span class="flex items-center gap-1">
                                                <Clock class="h-3 w-3" />
                                                {{ isRecentDate(txn.created_at) ? formatRelativeTime(txn.created_at) : formatDate(txn.created_at) }}
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ formatFullDateTime(txn.created_at) }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </template>
                    </CardWidget>
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                    <DollarSign class="h-12 w-12 text-muted-foreground/30 mb-4" />
                    <h3 class="text-lg font-medium">No transactions found</h3>
                    <p class="text-sm text-muted-foreground mt-1">
                        {{ hasActiveFilters ? 'Try adjusting your filters' : 'Transactions will appear here when customers make payments' }}
                    </p>
                </div>

                <!-- Pagination -->
                <Pagination
                    v-if="props.transactionItems.data.length > 0"
                    :current-page="pagination.current_page"
                    :last-page="pagination.last_page"
                    :per-page="pagination.per_page"
                    :total="pagination.total"
                    @page-change="handlePageChange"
                    @per-page-change="handlePerPageChange"
                />
            </div>
        </div>
    </div>
</template>
