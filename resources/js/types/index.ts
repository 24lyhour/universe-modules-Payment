// Payment Module Types

export type TransactionStatus = 'pending' | 'processing' | 'completed' | 'failed';
export type TransactionType = 'payment' | 'refund';
export type PaymentMethod = 'wallet' | 'cash' | 'aba_payway' | 'credit_card';

export interface TransactionItem {
    id: number;
    uuid: string;
    transaction_number: string;
    type: TransactionType;
    payment_method: PaymentMethod | string;
    amount: number;
    fee: number;
    net_amount: number;
    currency: string;
    status: TransactionStatus;
    gateway_transaction_id: string | null;
    gateway_response: Record<string, unknown> | null;
    notes: string | null;
    failure_reason: string | null;
    processed_at: string | null;
    failed_at: string | null;
    order?: OrderInfo | null;
    customer?: CustomerInfo | null;
    outlet?: OutletInfo | null;
    created_at: string;
    updated_at: string;
}

export interface OrderInfo {
    id: number;
    uuid: string;
    order_number: string;
    total_amount: number;
    status: string;
    payment_status: string;
}

export interface CustomerInfo {
    id: number;
    name: string;
    email: string;
    phone?: string;
}

export interface OutletInfo {
    id: number;
    name: string;
}

export interface SelectOption {
    value: string;
    label: string;
}

// Pagination
export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: PaginationMeta;
}

// Stats
export interface TransactionStats {
    total: number;
    pending: number;
    processing: number;
    completed: number;
    failed: number;
    total_revenue: number;
    total_refunded: number;
}

// Props
export interface TransactionIndexProps {
    transactionItems: PaginatedResponse<TransactionItem>;
    filters: {
        search?: string;
        status?: string;
        type?: string;
        payment_method?: string;
        outlet_id?: number;
        date_from?: string;
        date_to?: string;
    };
    stats: TransactionStats;
    outlets: OutletInfo[];
    statuses: SelectOption[];
    paymentMethods: SelectOption[];
}

export interface TransactionShowProps {
    transaction: TransactionItem;
}

// Payment Settings
export interface AcceptedBrand {
    name: string;
    logo: string;
}

export interface PaymentMethodConfig {
    id: string;
    name: string;
    description: string;
    enabled: boolean;
    icon: string;
    has_credentials?: boolean;
    merchant_id?: string;
    base_url?: string;
    is_sandbox?: boolean;
    coming_soon?: boolean;
    accepted_brands?: AcceptedBrand[];
}

export interface PaymentSettings {
    payment_methods: PaymentMethodConfig[];
}

export interface PaymentMethodStats {
    total_count: number;
    completed_count: number;
    failed_count: number;
    pending_count: number;
    revenue: number;
    refunded: number;
}

export interface PaymentStatsData {
    total_transactions: number;
    total_revenue: number;
    by_method: Record<string, PaymentMethodStats>;
}

export interface OutletPayWayItem {
    uuid: string;
    name: string;
    merchant_id: string;
    enabled: boolean;
}

export interface OutletPayWaySummary {
    total_outlets: number;
    configured_count: number;
    enabled_count: number;
    outlets: OutletPayWayItem[];
}

export interface PaymentSettingsProps {
    settings: PaymentSettings;
    stats: PaymentStatsData;
    outletPayway: OutletPayWaySummary;
}
