export default function ProductGridSkeleton({ count = 8 }: { count?: number }) {
    return (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4" aria-label="Loading products">
            {Array.from({ length: count }, (_, i) => (
                <div key={i} className="animate-pulse">
                    <div className="aspect-square rounded-xl bg-[var(--store-card-hover)]" />
                    <div className="space-y-2 px-1 pt-3">
                        <div className="h-3 w-2/3 rounded-lg bg-[var(--store-card-hover)]" />
                        <div className="h-4 w-1/3 rounded-lg bg-[var(--store-card-hover)]" />
                    </div>
                </div>
            ))}
        </div>
    );
}
