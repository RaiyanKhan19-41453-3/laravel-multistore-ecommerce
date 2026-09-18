/**
 * Deterministic "random" photo for products without their own image.
 * Seeded per product so the photo is stable across renders.
 */
export function placeholderImage(seed: string | number, size = 800): string {
    return `https://picsum.photos/seed/store-${seed}/${size}/${size}`;
}
