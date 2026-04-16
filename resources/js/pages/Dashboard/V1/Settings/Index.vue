<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import {
    CreditCard,
    Wallet,
    Banknote,
    ShieldCheck,
    AlertTriangle,
    ArrowLeftRight,
    DollarSign,
    Store,
    CheckCircle,
    ExternalLink,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import { StatsCard } from '@/components/shared';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { PaymentSettingsProps } from '@payment/types';

defineOptions({
    layout: (h: (type: unknown, props: unknown, children: unknown) => VNode, page: VNode) =>
        h(AppLayout, { breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Payment Settings', href: '/dashboard/payment-settings' },
        ]}, () => page),
});

const props = defineProps<PaymentSettingsProps>();

const iconMap: Record<string, typeof CreditCard> = {
    'credit-card': CreditCard,
    wallet: Wallet,
    banknote: Banknote,
};

const getIcon = (iconName: string) => iconMap[iconName] || CreditCard;

const logoMap: Record<string, string> = {
    aba_payway: '/images/payments/aba_payway.svg',
    credit_card: '/images/payments/credit_card.svg',
    cash: '/images/payments/cash.svg',
    wallet: '/images/payments/wallet.svg',
};

const getLogoPath = (methodId: string): string | null => logoMap[methodId] || null;

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};

const getMethodStats = (methodId: string) => {
    return props.stats.by_method[methodId] || null;
};
</script>

<template>
    <Head title="Payment Settings" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Payment Settings</h1>
            <p class="text-muted-foreground">Configure payment methods and gateway settings</p>
        </div>

        <!-- Overview Stats -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                title="Total Transactions"
                :value="stats.total_transactions"
                :icon="ArrowLeftRight"
            />
            <StatsCard
                title="Total Revenue"
                :value="formatCurrency(stats.total_revenue)"
                :icon="DollarSign"
            />
            <StatsCard
                title="PayWay Outlets"
                :value="`${outletPayway.enabled_count} / ${outletPayway.total_outlets}`"
                :icon="Store"
            />
            <StatsCard
                title="Active Methods"
                :value="settings.payment_methods.filter(m => m.enabled && !m.coming_soon).length"
                :icon="CheckCircle"
            />
        </div>

        <!-- Payment Methods -->
        <div>
            <h2 class="text-lg font-semibold mb-4">Payment Methods</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card
                    v-for="method in settings.payment_methods"
                    :key="method.id"
                    :class="[
                        'relative transition-all',
                        method.enabled ? 'border-primary/30 shadow-sm' : 'opacity-75',
                        method.coming_soon ? 'opacity-60' : '',
                    ]"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    v-if="getLogoPath(method.id)"
                                    class="h-12 w-12 rounded-xl overflow-hidden"
                                >
                                    <img
                                        :src="getLogoPath(method.id)!"
                                        :alt="method.name"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                <div
                                    v-else
                                    class="flex h-12 w-12 items-center justify-center rounded-xl"
                                    :class="method.enabled ? 'bg-primary/10' : 'bg-muted'"
                                >
                                    <component
                                        :is="getIcon(method.icon)"
                                        class="h-6 w-6"
                                        :class="method.enabled ? 'text-primary' : 'text-muted-foreground'"
                                    />
                                </div>
                                <div>
                                    <CardTitle class="text-base">{{ method.name }}</CardTitle>
                                    <CardDescription class="text-xs mt-0.5">{{ method.description }}</CardDescription>
                                </div>
                            </div>
                            <Switch
                                :model-value="method.enabled"
                                :disabled="method.coming_soon"
                            />
                        </div>
                    </CardHeader>
                    <CardContent class="pt-0 space-y-3">
                        <!-- Accepted Brands -->
                        <div v-if="method.accepted_brands?.length" class="flex items-center gap-2">
                            <img
                                v-for="brand in method.accepted_brands"
                                :key="brand.name"
                                :src="brand.logo"
                                :alt="brand.name"
                                class="h-8 rounded"
                            />
                        </div>

                        <Separator />

                        <!-- Badges -->
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge v-if="method.coming_soon" variant="outline" class="text-xs">
                                Coming Soon
                            </Badge>
                            <Badge v-if="method.is_sandbox" variant="secondary" class="text-xs">
                                <AlertTriangle class="h-3 w-3 mr-1" />
                                Sandbox
                            </Badge>
                            <Badge v-if="method.has_credentials" variant="outline" class="text-xs">
                                <ShieldCheck class="h-3 w-3 mr-1 text-green-600" />
                                Configured
                            </Badge>
                            <Badge v-if="method.enabled && !method.coming_soon" variant="default" class="text-xs">
                                Active
                            </Badge>
                            <Badge v-else-if="!method.coming_soon" variant="secondary" class="text-xs">
                                Disabled
                            </Badge>
                        </div>

                        <!-- Merchant ID -->
                        <div v-if="method.merchant_id" class="flex items-center gap-2">
                            <ShieldCheck class="h-3.5 w-3.5 text-green-600" />
                            <span class="text-xs text-muted-foreground font-mono">Merchant: {{ method.merchant_id }}</span>
                        </div>

                        <!-- Transaction Stats -->
                        <template v-if="getMethodStats(method.id)">
                            <Separator />
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div>
                                    <p class="text-lg font-semibold">{{ getMethodStats(method.id)!.total_count }}</p>
                                    <p class="text-xs text-muted-foreground">Transactions</p>
                                </div>
                                <div>
                                    <p class="text-lg font-semibold text-green-600">{{ getMethodStats(method.id)!.completed_count }}</p>
                                    <p class="text-xs text-muted-foreground">Completed</p>
                                </div>
                                <div>
                                    <p class="text-lg font-semibold text-red-500">{{ getMethodStats(method.id)!.failed_count }}</p>
                                    <p class="text-xs text-muted-foreground">Failed</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2">
                                <span class="text-xs text-muted-foreground">Revenue</span>
                                <span class="text-sm font-semibold">{{ formatCurrency(getMethodStats(method.id)!.revenue) }}</span>
                            </div>
                        </template>

                        <!-- No stats yet -->
                        <div v-else-if="!method.coming_soon" class="text-center py-2">
                            <p class="text-xs text-muted-foreground">No transactions yet</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Outlet PayWay Configuration -->
        <div>
            <h2 class="text-lg font-semibold mb-4">Outlet PayWay Configuration</h2>
            <Card>
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-base flex items-center gap-2">
                                <img src="/images/payments/aba_payway.svg" alt="ABA PayWay" class="h-5 w-5 rounded-full object-cover" />
                                Merchant Accounts
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                {{ outletPayway.configured_count }} of {{ outletPayway.total_outlets }} outlets configured
                            </CardDescription>
                        </div>
                        <Badge variant="outline">
                            {{ outletPayway.enabled_count }} Active
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="outletPayway.outlets.length === 0" class="text-center py-6 text-muted-foreground text-sm">
                        No outlets have PayWay configured yet
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="outlet in outletPayway.outlets"
                            :key="outlet.uuid"
                            class="flex items-center justify-between rounded-lg border px-4 py-3"
                        >
                            <div class="flex items-center gap-3">
                                <Store class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p class="text-sm font-medium">{{ outlet.name }}</p>
                                    <p class="text-xs text-muted-foreground font-mono">{{ outlet.merchant_id }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <Badge :variant="outlet.enabled ? 'default' : 'secondary'" class="text-xs">
                                    {{ outlet.enabled ? 'Enabled' : 'Disabled' }}
                                </Badge>
                                <Link
                                    :href="`/dashboard/outlets/${outlet.uuid}`"
                                    class="text-muted-foreground hover:text-foreground"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
