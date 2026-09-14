import { Button } from '@/components/ui/button';
import { formatPrice } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Printer } from 'lucide-react';

interface Label {
    name: string;
    variant: string | null;
    sku: string;
    price: number;
    code: string;
    svg: string;
}

function BarcodeImage({ svg, className }: { svg: string; className?: string }) {
    return <div className={className} dangerouslySetInnerHTML={{ __html: svg }} />;
}

export default function LabelSheet({
    labels,
    layout,
    layoutConfig,
    skipped,
    truncated,
    maxLabels,
}: {
    labels: Label[];
    layout: string;
    layoutConfig: { label: string; columns: number; kind: string };
    skipped: string[];
    truncated: boolean;
    maxLabels: number;
}) {
    const isThermal = layoutConfig.kind === 'thermal';
    const columns = isThermal ? 1 : layoutConfig.columns;

    return (
        <>
            <Head title={`Barcode Labels (${labels.length})`} />

            <div className="label-toolbar min-h-screen bg-neutral-100 p-6 dark:bg-neutral-950">
                <div className="mx-auto mb-6 flex max-w-5xl flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Link href={route('admin.products.labels')}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-1 h-4 w-4" /> Back
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-lg font-bold">Barcode Labels</h1>
                            <p className="text-sm text-neutral-500">
                                {labels.length} labels · {layoutConfig.label}
                            </p>
                        </div>
                    </div>
                    <Button onClick={() => window.print()} disabled={labels.length === 0}>
                        <Printer className="mr-2 h-4 w-4" /> Print
                    </Button>
                </div>

                {(skipped.length > 0 || truncated) && (
                    <div className="mx-auto mb-6 max-w-5xl space-y-2">
                        {skipped.map((message, i) => (
                            <div key={i} className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                <span>{message}</span>
                            </div>
                        ))}
                        {truncated && (
                            <div className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                <span>Capped at {maxLabels} labels per run. Print the rest separately.</span>
                            </div>
                        )}
                    </div>
                )}

                <div className="label-sheet mx-auto max-w-5xl bg-white p-6 shadow dark:bg-white">
                    {labels.length === 0 ? (
                        <p className="py-12 text-center text-sm text-neutral-500">
                            Nothing to print. Go back and pick at least one printable item.
                        </p>
                    ) : (
                        <div
                            className="label-grid"
                            style={{
                                display: 'grid',
                                gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
                                gap: isThermal ? '0' : '8px',
                            }}
                        >
                            {labels.map((label, i) => (
                                <div
                                    key={i}
                                    className={isThermal ? 'thermal-label' : 'a4-label'}
                                    style={
                                        isThermal
                                            ? { width: '50mm', height: '30mm' }
                                            : undefined
                                    }
                                >
                                    <p className="label-name">{label.name}</p>
                                    {label.variant && <p className="label-variant">{label.variant}</p>}
                                    <BarcodeImage svg={label.svg} className="label-bars" />
                                    <p className="label-code">{label.code}</p>
                                    <p className="label-price">{formatPrice(label.price)}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <style>{`
                .a4-label, .thermal-label {
                    border: 1px dashed #d4d4d4;
                    border-radius: 4px;
                    padding: 6px;
                    text-align: center;
                    color: #000;
                    background: #fff;
                    overflow: hidden;
                }
                .thermal-label {
                    border-style: solid;
                    border-radius: 0;
                    padding: 2mm;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                }
                .label-name {
                    font-size: 11px;
                    font-weight: 700;
                    line-height: 1.2;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    max-width: 100%;
                }
                .thermal-label .label-name { font-size: 9px; }
                .label-variant {
                    font-size: 10px;
                    color: #525252;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    max-width: 100%;
                }
                .thermal-label .label-variant { font-size: 8px; }
                .label-bars svg {
                    width: 100%;
                    height: auto;
                    max-height: 50px;
                }
                .thermal-label .label-bars svg { max-height: 9mm; }
                .label-code {
                    font-family: ui-monospace, monospace;
                    font-size: 10px;
                    letter-spacing: 0.05em;
                }
                .thermal-label .label-code { font-size: 8px; }
                .label-price {
                    font-size: 11px;
                    font-weight: 700;
                }
                .thermal-label .label-price { font-size: 9px; }
                @media print {
                    .label-toolbar > *:not(.label-sheet) { display: none !important; }
                    .label-toolbar {
                        background: #fff !important;
                        padding: 0 !important;
                        min-height: auto !important;
                    }
                    .label-sheet {
                        box-shadow: none !important;
                        max-width: none !important;
                        padding: 0 !important;
                        margin: 0 !important;
                    }
                    .a4-label { break-inside: avoid; }
                    .thermal-label {
                        break-inside: avoid;
                        break-after: page;
                    }
                    .thermal-label:last-child { break-after: auto; }
                    @page {
                        size: ${isThermal ? '50mm 30mm' : 'A4'};
                        margin: ${isThermal ? '0' : '10mm'};
                    }
                }
            `}</style>
        </>
    );
}
