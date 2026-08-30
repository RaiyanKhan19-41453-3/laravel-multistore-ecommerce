import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    rectSortingStrategy,
    arrayMove,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ImagePlus, Save, Star, Trash2, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

interface ImageData {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    alt_text: string | null;
    sort_order: number;
    is_primary: boolean;
    paths: Record<string, string>;
    urls: Record<string, string>;
}

interface PendingImage {
    key: string;
    file: File;
    preview: string;
}

interface ImageUploadProps {
    productId: number;
    images: ImageData[];
    variantId?: number;
    label?: string;
}

function SortableExistingImage({
    image,
    onDelete,
    onSetPrimary,
    isPendingPrimary,
}: {
    image: ImageData;
    productId: number;
    onDelete: (id: number) => void;
    onSetPrimary: (id: number) => void;
    isPendingPrimary: boolean;
}) {
    const [altText, setAltText] = useState(image.alt_text ?? '');
    const [saving, setSaving] = useState(false);

    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: image.id,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
        zIndex: isDragging ? 10 : 0,
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    const saveAltText = () => {
        if (altText === (image.alt_text ?? '')) return;
        setSaving(true);
        router.put(
            route('admin.products.images.update', [productId, image.id]),
            { alt_text: altText || null },
            {
                preserveState: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`relative overflow-hidden rounded-lg border-2 transition ${
                image.is_primary || isPendingPrimary
                    ? 'border-amber-400 dark:border-amber-500'
                    : 'border-neutral-200 dark:border-neutral-700'
            } ${isDragging ? 'z-10' : ''}`}
        >
            <div
                {...listeners}
                {...attributes}
                className="aspect-square cursor-grab bg-neutral-100 active:cursor-grabbing dark:bg-neutral-800"
            >
                <img
                    src={image.urls.thumbnail || image.urls.original}
                    alt={image.alt_text || image.filename}
                    className="h-full w-full object-cover"
                />
            </div>

            <div className="absolute top-1 right-1 flex gap-1">
                <button
                    type="button"
                    onClick={() => onSetPrimary(image.id)}
                    className="rounded-full bg-black/50 p-1.5 text-white hover:bg-black/70"
                    title="Set as primary"
                >
                    <Star
                        className={`h-3.5 w-3.5 ${image.is_primary || isPendingPrimary ? 'fill-amber-400 text-amber-400' : ''}`}
                    />
                </button>
                <button
                    type="button"
                    onClick={() => onDelete(image.id)}
                    className="rounded-full bg-black/50 p-1.5 text-white hover:bg-red-500/80"
                    title="Delete image"
                >
                    <Trash2 className="h-3.5 w-3.5" />
                </button>
            </div>

            {(image.is_primary || isPendingPrimary) && (
                <div className="absolute top-1 left-1">
                    <span className="inline-flex items-center rounded-full bg-amber-400 px-1.5 py-0.5 text-[10px] font-medium text-amber-900">
                        {isPendingPrimary && !image.is_primary ? 'New Primary' : 'Primary'}
                    </span>
                </div>
            )}

            <div className="space-y-1.5 px-2 py-1.5">
                <p className="truncate text-xs text-neutral-600 dark:text-neutral-400">{image.filename}</p>
                <p className="text-[10px] text-neutral-400">
                    {image.width && image.height ? `${image.width}×${image.height}` : ''}{' '}
                    {formatFileSize(image.size)}
                </p>
                <div className="relative">
                    <Input
                        value={altText}
                        onChange={(e) => setAltText(e.target.value)}
                        onBlur={saveAltText}
                        placeholder="Alt text..."
                        className="h-7 text-xs"
                    />
                    {saving && (
                        <div className="absolute top-1/2 right-2 -translate-y-1/2">
                            <div className="h-3 w-3 animate-spin rounded-full border border-neutral-300 border-t-neutral-600" />
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function SortablePendingImage({
    pending,
    onRemove,
}: {
    pending: PendingImage;
    onRemove: (key: string) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: pending.key,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
        zIndex: isDragging ? 10 : 0,
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`relative overflow-hidden rounded-lg border-2 border-dashed border-blue-300 dark:border-blue-600 ${isDragging ? 'z-10' : ''}`}
        >
            <div
                {...listeners}
                {...attributes}
                className="aspect-square cursor-grab bg-neutral-100 active:cursor-grabbing dark:bg-neutral-800"
            >
                <img
                    src={pending.preview}
                    alt={pending.file.name}
                    className="h-full w-full object-cover"
                />
            </div>

            <div className="absolute top-1 right-1">
                <button
                    type="button"
                    onClick={() => onRemove(pending.key)}
                    className="rounded-full bg-black/50 p-1.5 text-white hover:bg-red-500/80"
                    title="Remove"
                >
                    <X className="h-3.5 w-3.5" />
                </button>
            </div>

            <div className="absolute top-1 left-1">
                <span className="inline-flex items-center rounded-full bg-blue-500 px-1.5 py-0.5 text-[10px] font-medium text-white">
                    Pending
                </span>
            </div>

            <div className="space-y-1 px-2 py-1.5">
                <p className="truncate text-xs text-neutral-600 dark:text-neutral-400">{pending.file.name}</p>
                <p className="text-[10px] text-neutral-400">{formatFileSize(pending.file.size)}</p>
            </div>
        </div>
    );
}

export default function ImageUpload({ productId, images, variantId, label = 'Images' }: ImageUploadProps) {
    const [pendingImages, setPendingImages] = useState<PendingImage[]>([]);
    const [orderedImages, setOrderedImages] = useState<ImageData[]>(images);
    const [deletedIds, setDeletedIds] = useState<number[]>([]);
    const [pendingPrimaryId, setPendingPrimaryId] = useState<number | null>(null);
    const [uploading, setUploading] = useState(false);
    const [savingOrder, setSavingOrder] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        setOrderedImages(images);
        setDeletedIds([]);
        setPendingPrimaryId(null);
    }, [images]);

    const visibleImages = orderedImages.filter((img) => !deletedIds.includes(img.id));
    const orderChanged = JSON.stringify(visibleImages.map((img) => img.id)) !== JSON.stringify(images.filter((img) => !deletedIds.includes(img.id)).map((img) => img.id));
    const primaryChanged = pendingPrimaryId !== null;
    const hasPendingChanges = deletedIds.length > 0 || orderChanged || primaryChanged;

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 15 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const addFiles = useCallback(
        (files: FileList | null) => {
            if (!files || files.length === 0) return;

            const newPending: PendingImage[] = [];
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (!file.type.startsWith('image/')) continue;
                if (file.size > 10 * 1024 * 1024) continue;
                newPending.push({
                    key: `pending-${Date.now()}-${i}`,
                    file,
                    preview: URL.createObjectURL(file),
                });
            }

            setPendingImages((prev) => [...prev, ...newPending]);

            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        },
        [],
    );

    const removePending = useCallback((key: string) => {
        setPendingImages((prev) => {
            const removed = prev.find((p) => p.key === key);
            if (removed) URL.revokeObjectURL(removed.preview);
            return prev.filter((p) => p.key !== key);
        });
    }, []);

    const savePending = useCallback(() => {
        if (pendingImages.length === 0) return;

        setUploading(true);

        const formData = new FormData();
        pendingImages.forEach((p) => {
            formData.append('images[]', p.file);
        });
        if (variantId) {
            formData.append('product_variant_id', String(variantId));
        }

        router.post(route('admin.products.images.store', productId), formData, {
            preserveState: true,
            onFinish: () => {
                pendingImages.forEach((p) => URL.revokeObjectURL(p.preview));
                setPendingImages([]);
                setUploading(false);
            },
        });
    }, [productId, variantId, pendingImages]);

    const handleDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            setDragOver(false);
            addFiles(e.dataTransfer.files);
        },
        [addFiles],
    );

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
    }, []);

    const handleDelete = (imageId: number) => {
        setDeletedIds((prev) => [...prev, imageId]);
    };

    const handleRestore = (imageId: number) => {
        setDeletedIds((prev) => prev.filter((id) => id !== imageId));
    };

    const handleSetPrimary = (imageId: number) => {
        setPendingPrimaryId(imageId);
    };

    const handleExistingDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const oldIndex = orderedImages.findIndex((img) => img.id === active.id);
        const newIndex = orderedImages.findIndex((img) => img.id === over.id);
        setOrderedImages(arrayMove(orderedImages, oldIndex, newIndex));
    };

    const saveChanges = () => {
        setSavingOrder(true);

        const doSaveReorder = () => {
            if (orderChanged) {
                router.put(
                    route('admin.products.images.reorder', productId),
                    {
                        images: visibleImages.map((img, i) => ({ id: img.id, sort_order: i })),
                    },
                    {
                        preserveState: true,
                        onFinish: () => setSavingOrder(false),
                    },
                );
            } else {
                setSavingOrder(false);
            }
        };

        const doSavePrimary = () => {
            if (primaryChanged) {
                router.put(
                    route('admin.products.images.update', [productId, pendingPrimaryId]),
                    { is_primary: true },
                    {
                        preserveState: true,
                        onFinish: () => {
                            setPendingPrimaryId(null);
                            doSaveReorder();
                        },
                    },
                );
            } else {
                doSaveReorder();
            }
        };

        if (deletedIds.length > 0) {
            router.delete(route('admin.products.images.bulk-destroy', productId), {
                data: { image_ids: deletedIds },
                preserveState: true,
                onFinish: () => {
                    setDeletedIds([]);
                    doSavePrimary();
                },
            });
        } else {
            doSavePrimary();
        }
    };

    const handlePendingDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        setPendingImages((prev) => {
            const oldIndex = prev.findIndex((p) => p.key === active.id);
            const newIndex = prev.findIndex((p) => p.key === over.id);
            return arrayMove(prev, oldIndex, newIndex);
        });
    };

    return (
        <div className="space-y-4">
            {visibleImages.length > 0 && (
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleExistingDragEnd}>
                    <SortableContext items={visibleImages.map((img) => img.id)} strategy={rectSortingStrategy}>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                            {visibleImages.map((image) => (
                                <SortableExistingImage
                                    key={image.id}
                                    image={image}
                                    productId={productId}
                                    onDelete={handleDelete}
                                    onSetPrimary={handleSetPrimary}
                                    isPendingPrimary={pendingPrimaryId === image.id}
                                />
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            )}

            {deletedIds.length > 0 && (
                <div className="space-y-2">
                    <p className="text-sm font-medium text-red-600 dark:text-red-400">
                        {deletedIds.length} image{deletedIds.length !== 1 ? 's' : ''} marked for deletion
                    </p>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                        {orderedImages
                            .filter((img) => deletedIds.includes(img.id))
                            .map((image) => (
                                <div
                                    key={image.id}
                                    className="relative overflow-hidden rounded-lg border-2 border-dashed border-red-300 opacity-50 dark:border-red-700"
                                >
                                    <div className="aspect-square bg-neutral-100 dark:bg-neutral-800">
                                        <img
                                            src={image.urls.thumbnail || image.urls.original}
                                            alt={image.filename}
                                            className="h-full w-full object-cover grayscale"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => handleRestore(image.id)}
                                        className="absolute inset-0 flex items-center justify-center bg-black/40 text-sm font-medium text-white opacity-0 transition hover:opacity-100"
                                    >
                                        Restore
                                    </button>
                                </div>
                            ))}
                    </div>
                </div>
            )}

            {hasPendingChanges && (
                <div className="flex items-center justify-end gap-2">
                    <Button
                        onClick={() => {
                            setDeletedIds([]);
                            setOrderedImages(images);
                            setPendingPrimaryId(null);
                        }}
                        variant="outline"
                        size="sm"
                    >
                        Cancel All
                    </Button>
                    <Button
                        onClick={saveChanges}
                        disabled={savingOrder}
                        size="sm"
                        className="gap-1.5"
                    >
                        {savingOrder ? (
                            <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                        ) : (
                            <Save className="h-3.5 w-3.5" />
                        )}
                        {savingOrder ? 'Saving...' : 'Save Changes'}
                    </Button>
                </div>
            )}

            {pendingImages.length > 0 && (
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            {pendingImages.length} image{pendingImages.length !== 1 ? 's' : ''} ready to upload
                        </p>
                        <Button
                            onClick={savePending}
                            disabled={uploading}
                            size="sm"
                            className="gap-1.5"
                        >
                            {uploading ? (
                                <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                            ) : (
                                <Save className="h-3.5 w-3.5" />
                            )}
                            {uploading ? 'Uploading...' : 'Save Images'}
                        </Button>
                    </div>

                    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handlePendingDragEnd}>
                        <SortableContext items={pendingImages.map((p) => p.key)} strategy={rectSortingStrategy}>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                {pendingImages.map((pending) => (
                                    <SortablePendingImage
                                        key={pending.key}
                                        pending={pending}
                                        onRemove={removePending}
                                    />
                                ))}
                            </div>
                        </SortableContext>
                    </DndContext>
                </div>
            )}

            <div
                onDrop={handleDrop}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onClick={() => fileInputRef.current?.click()}
                className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 transition ${
                    dragOver
                        ? 'border-neutral-900 bg-neutral-50 dark:border-neutral-100 dark:bg-neutral-800'
                        : 'border-neutral-300 hover:border-neutral-400 dark:border-neutral-600 dark:hover:border-neutral-500'
                }`}
            >
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    className="hidden"
                    onChange={(e) => addFiles(e.target.files)}
                />
                {uploading ? (
                    <div className="flex items-center gap-2 text-sm text-neutral-500">
                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-neutral-300 border-t-neutral-600" />
                        Uploading...
                    </div>
                ) : (
                    <>
                        <ImagePlus className="mb-2 h-8 w-8 text-neutral-400" />
                        <p className="text-sm text-neutral-600 dark:text-neutral-400">
                            <span className="font-medium">Click to upload</span> or drag and drop
                        </p>
                        <p className="mt-1 text-xs text-neutral-500">JPEG, PNG, WebP, GIF up to 10MB</p>
                    </>
                )}
            </div>
        </div>
    );
}
