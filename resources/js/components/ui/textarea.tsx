import { cn } from '@/lib/utils';
import { forwardRef, type TextareaHTMLAttributes } from 'react';

const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(({ className, ...props }, ref) => {
    return (
        <textarea
            className={cn(
                'flex min-h-[60px] w-full rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-neutral-500 focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder:text-neutral-400 dark:focus:border-neutral-300 dark:focus:ring-neutral-300',
                className,
            )}
            ref={ref}
            {...props}
        />
    );
});

Textarea.displayName = 'Textarea';

export { Textarea };
