export function formatPrice(value: number | string): string {
    return `\u09F3${Number(value).toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })}`;
}
