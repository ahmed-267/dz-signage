/**
 * Billing payload shapes. `App\Support\Billing\BillingEntitlement` is the only
 * place that decides entitlement, licence counts and status — these types just
 * describe what it hands to Inertia.
 */

export type BillingConnectedScreen = {
    id: number;
    name: string;
};

export type BillingAmountDisplay = {
    amount: number | null;
    formatted: string | null;
    currency: string;
};

export type MarketingPlan = {
    key: string;
    name: string;
    tagline: string;
    enterprise: boolean;
    popular: boolean;
    cta: string;
    screen_limit: number | null;
    storage_gb: number | null;
    team_limit: number | null;
    feature_labels: string[];
    monthly: BillingAmountDisplay | null;
    yearly: BillingAmountDisplay | null;
    yearly_monthly_equivalent: BillingAmountDisplay | null;
};

export type MarketingComparisonRow = {
    feature: string;
    starter: boolean | string;
    business: boolean | string;
    enterprise: boolean | string;
};

export type MarketingPlanCatalog = {
    plans: MarketingPlan[];
    comparison: MarketingComparisonRow[];
    currency: string;
};

export type MarketingPrice = {
    interval: string;
    formatted: string;
    currency: string | null;
    unit_amount: number | null;
};

export type MarketingPricing = {
    configured: boolean;
    prices: MarketingPrice[];
    min_licenses: number;
    max_licenses: number;
};

export type BillingSummary = {
    configured: boolean;
    enforce: boolean;
    status: string;
    status_label: string;
    has_subscription: boolean;
    has_entitlement: boolean;
    stripe_id?: string | null;
    subscription_id?: string | null;
    interval: string | null;
    interval_label: string | null;
    plan_key?: string | null;
    plan_name?: string | null;
    storage_gb?: number | null;
    storage_limit_bytes?: number | null;
    team_limit?: number | null;
    team_used?: number;
    features?: string[];
    /** Purchased Screen licences (Stripe subscription quantity). */
    licensed: number;
    /** Screens with a non-revoked device. */
    used: number;
    remaining: number;
    screen_limit?: number;
    quantity: number | null;
    on_grace_period: boolean;
    ends_at: string | null;
    trial_ends_at?: string | null;
    past_due: boolean;
    connected_screens: BillingConnectedScreen[];
};

/** A Stripe or catalog Price. `formatted` is null when unavailable. */
export type BillingPrice = {
    interval: string;
    price_id: string | null;
    unit_amount: number | null;
    currency: string | null;
    formatted: string | null;
    source?: string;
};

export type BillingInvoiceRow = {
    id: number;
    number: string | null;
    status: string;
    currency: string | null;
    total: number;
    total_formatted: string;
    hosted_invoice_url: string | null;
    invoice_pdf: string | null;
    billed_at: string | null;
};

export type BillingInvoicePage = {
    data: BillingInvoiceRow[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from?: number | null;
        to?: number | null;
    };
    links: {
        prev: string | null;
        next: string | null;
    };
};

export type BillingIndexProps = {
    summary: BillingSummary;
    plans: MarketingPlan[];
    comparison: MarketingComparisonRow[];
    prices: BillingPrice[];
    licence_limits: { min: number; max: number };
    unavailable_message: string | null;
    stripe_configured?: boolean;
    invoices: BillingInvoicePage;
    permissions: { can_manage: boolean };
    checkout: 'success' | 'cancelled' | null;
};

/** Screen licence usage shown on `/app/screens`, when the viewer can see billing. */
export type ScreenLicenceSummary = {
    used: number;
    licensed: number;
    remaining: number;
    manage_url: string;
};

type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
};

export type Paginated<T> = {
    data: T[];
    meta?: PaginatedMeta;
    /** @deprecated Prefer `meta`; kept for transitional payloads. */
    links?: PaginatorLink[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
};

export type FilterOption = {
    value: string;
    label: string;
};

export type AdminSubscriptionWorkspace = {
    id: number;
    name: string;
    slug?: string | null;
    stripe_id?: string | null;
    owner_name?: string | null;
    owner_email?: string | null;
};

export type AdminSubscriptionRow = {
    id: number;
    stripe_id: string | null;
    stripe_status: string | null;
    status: string;
    status_label: string;
    type: string | null;
    interval: string | null;
    plan_key?: string | null;
    plan_name?: string | null;
    stripe_price: string | null;
    quantity: number | null;
    licensed: number;
    used: number;
    on_grace_period: boolean;
    trial_ends_at: string | null;
    ends_at: string | null;
    created_at: string | null;
    workspace: AdminSubscriptionWorkspace | null;
};

export type AdminSubscriptionItem = {
    id: number;
    stripe_id: string | null;
    stripe_price: string | null;
    quantity: number | null;
};

export type AdminSubscriptionDetail = AdminSubscriptionRow & {
    items?: AdminSubscriptionItem[];
};

export type AdminSubscriptionInvoiceRow = {
    id: number;
    number: string | null;
    status: string;
    total: number;
    total_formatted: string;
    hosted_invoice_url: string | null;
    billed_at: string | null;
};

export type AdminSubscriptionsProps = {
    title?: string;
    description?: string;
    billing_unavailable?: boolean;
    configured?: boolean;
    enforced?: boolean;
    kind?: 'subscriptions' | 'subscription';
    plans?: Array<{ key: string; name: string; enterprise?: boolean }>;
    comparison?: unknown[];
    can_manage_billing?: boolean;
    subscriptions?: Paginated<AdminSubscriptionRow>;
    filters?: {
        q?: string;
        status?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
    statuses?: FilterOption[];
    /** Present only when `kind === 'subscription'`. */
    subscription?: AdminSubscriptionDetail;
    workspace?: {
        id: number;
        name: string;
        slug?: string | null;
        billing?: BillingSummary;
    };
    invoices?: AdminSubscriptionInvoiceRow[];
};

export type AdminInvoiceRow = {
    id: number;
    stripe_invoice_id: string | null;
    number: string | null;
    status: string;
    currency: string | null;
    total: number;
    total_formatted: string;
    hosted_invoice_url: string | null;
    invoice_pdf: string | null;
    billed_at: string | null;
    workspace: {
        id: number;
        name: string;
        slug?: string | null;
    } | null;
};

export type AdminInvoicesProps = {
    title?: string;
    description?: string;
    billing_unavailable?: boolean;
    configured?: boolean;
    kind?: string;
    invoices?: Paginated<AdminInvoiceRow>;
    filters?: {
        q?: string;
        status?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
    statuses?: FilterOption[];
};
