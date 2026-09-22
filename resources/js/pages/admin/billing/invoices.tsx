import { Head, Link, router } from '@inertiajs/react';
import { Download, ExternalLink, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ListPagination } from '@/components/ui/list-pagination';
import { SortableTableHeader } from '@/components/ui/sortable-table-header';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useListSort } from '@/hooks/use-list-sort';
import { adminSelectClassName } from '@/lib/admin-select-class';
import { formatBillingDate, invoiceStatusTone } from '@/lib/billing';
import { cn } from '@/lib/utils';
import AdminBillingUnavailable from '@/pages/admin/billing/unavailable';
import { invoices as invoicesIndex } from '@/routes/admin';
import { show as showWorkspace } from '@/routes/admin/workspaces';
import type { AdminInvoiceRow, AdminInvoicesProps } from '@/types/billing';

export default function AdminInvoices(props: AdminInvoicesProps) {
    const rows = props.invoices?.data ?? [];

    // Truthful: with no Stripe keys no invoice could have been mirrored yet.
    if (props.billing_unavailable && rows.length === 0) {
        return (
            <AdminBillingUnavailable
                title={props.title ?? 'Invoices'}
                path="/admin/invoices"
                billing_unavailable={props.billing_unavailable}
                message={
                    props.description ??
                    'Invoicing will become available when platform billing is enabled.'
                }
            />
        );
    }

    return <InvoicesIndex {...props} />;
}

function InvoicesIndex({
    title = 'Invoices',
    description,
    invoices,
    filters = {},
    statuses = [],
}: AdminInvoicesProps) {
    const rows = invoices?.data ?? [];
    const meta = invoices?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
        from: null,
        to: null,
    };
    const [searchInput, setSearchInput] = useState(filters.q ?? '');
    const perPage = filters.per_page ?? meta.per_page ?? 20;

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: invoicesIndex.url(),
        filters: {
            q: filters.q,
            status: filters.status,
            sort: filters.sort,
            direction: filters.direction,
            per_page: perPage,
        },
        defaultSort: 'billed',
        defaultDirection: 'desc',
    });

    useEffect(() => {
        setSearchInput(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (searchInput === (filters.q ?? '')) {
                return;
            }
            navigate({ q: searchInput, page: 1 });
        }, 350);

        return () => window.clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce against filters snapshot
    }, [searchInput]);

    function navigate(
        patch: Partial<{
            q: string;
            status: string;
            sort: string;
            direction: string;
            per_page: number;
            page: number;
        }>,
    ) {
        router.get(
            invoicesIndex.url(),
            {
                q: patch.q ?? filters.q ?? '',
                status: patch.status ?? filters.status ?? 'all',
                sort: patch.sort ?? filters.sort ?? 'billed',
                direction: patch.direction ?? filters.direction ?? 'desc',
                per_page: patch.per_page ?? perPage,
                ...(patch.page ? { page: patch.page } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    return (
        <>
            <Head title={title} />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-invoices"
            >
                <AdminPageHeader title={title} description={description} />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1 sm:max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="admin-invoices-search"
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                            placeholder="Search invoice numbers or workspaces..."
                            className="pl-9"
                            aria-label="Search invoices"
                        />
                    </div>
                    {statuses.length > 0 ? (
                        <select
                            className={cn(adminSelectClassName, 'sm:w-52')}
                            value={filters.status ?? 'all'}
                            aria-label="Status filter"
                            data-test="admin-invoices-status"
                            onChange={(event) =>
                                navigate({
                                    status: event.target.value,
                                    page: 1,
                                })
                            }
                        >
                            <option value="all">All statuses</option>
                            {statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    ) : null}
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            All invoices
                        </CardTitle>
                        <CardDescription>
                            {meta.total} total. Mirrored from Stripe —
                            read-only.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {rows.length === 0 ? (
                            <p
                                className="text-muted-foreground text-sm"
                                data-test="admin-invoices-empty"
                            >
                                No invoices match this search.
                            </p>
                        ) : null}

                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead
                                            aria-sort={ariaSort('number')}
                                        >
                                            <SortableTableHeader
                                                label="Invoice"
                                                column="number"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead>Workspace</TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('billed')}
                                        >
                                            <SortableTableHeader
                                                label="Date"
                                                column="billed"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('total')}
                                        >
                                            <SortableTableHeader
                                                label="Amount"
                                                column="total"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('status')}
                                        >
                                            <SortableTableHeader
                                                label="Status"
                                                column="status"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Stripe
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((invoice) => (
                                        <TableRow
                                            key={invoice.id}
                                            data-test={`admin-invoice-row-${invoice.id}`}
                                        >
                                            <TableCell className="font-mono text-sm">
                                                {invoice.number ??
                                                    invoice.stripe_invoice_id ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell>
                                                {invoice.workspace ? (
                                                    <Link
                                                        href={showWorkspace.url(
                                                            {
                                                                workspace:
                                                                    invoice
                                                                        .workspace
                                                                        .id,
                                                            },
                                                        )}
                                                        className="hover:underline"
                                                    >
                                                        {invoice.workspace.name}
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {formatBillingDate(
                                                    invoice.billed_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm font-medium">
                                                {invoice.total_formatted}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    label={invoice.status}
                                                    tone={invoiceStatusTone(
                                                        invoice.status,
                                                    )}
                                                    className="capitalize"
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <InvoiceLinks
                                                    invoice={invoice}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {rows.map((invoice) => (
                                <div
                                    key={invoice.id}
                                    className="space-y-2 rounded-lg border p-4"
                                    data-test={`admin-invoice-card-${invoice.id}`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-mono text-sm">
                                                {invoice.number ??
                                                    invoice.stripe_invoice_id ??
                                                    '—'}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {invoice.workspace?.name ?? '—'}{' '}
                                                ·{' '}
                                                {formatBillingDate(
                                                    invoice.billed_at,
                                                )}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            label={invoice.status}
                                            tone={invoiceStatusTone(
                                                invoice.status,
                                            )}
                                            className="capitalize"
                                        />
                                    </div>
                                    <p className="font-mono text-sm font-medium">
                                        {invoice.total_formatted}
                                    </p>
                                    <InvoiceLinks invoice={invoice} />
                                </div>
                            ))}
                        </div>

                        {meta.last_page > 1 || meta.total > perPage ? (
                            <ListPagination
                                page={meta.current_page}
                                pageCount={meta.last_page}
                                total={meta.total}
                                from={meta.from ?? null}
                                to={meta.to ?? null}
                                perPage={perPage}
                                onPageChange={(page) => navigate({ page })}
                                onPerPageChange={(per_page) =>
                                    navigate({ per_page, page: 1 })
                                }
                            />
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function InvoiceLinks({ invoice }: { invoice: AdminInvoiceRow }) {
    if (!invoice.hosted_invoice_url && !invoice.invoice_pdf) {
        return <span className="text-muted-foreground text-xs">—</span>;
    }

    return (
        <div className="flex flex-wrap gap-1.5 md:justify-end">
            {invoice.hosted_invoice_url ? (
                <Button size="sm" variant="ghost" asChild>
                    <a
                        href={invoice.hosted_invoice_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={`View invoice ${invoice.number ?? invoice.id} on Stripe`}
                    >
                        <ExternalLink className="size-3.5" />
                        View
                    </a>
                </Button>
            ) : null}
            {invoice.invoice_pdf ? (
                <Button size="sm" variant="ghost" asChild>
                    <a
                        href={invoice.invoice_pdf}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={`Download PDF for invoice ${invoice.number ?? invoice.id}`}
                    >
                        <Download className="size-3.5" />
                        PDF
                    </a>
                </Button>
            ) : null}
        </div>
    );
}

AdminInvoices.layout = {
    breadcrumbs: [{ title: 'Invoices', href: invoicesIndex.url() }],
};
