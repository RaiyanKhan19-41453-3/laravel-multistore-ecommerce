import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useState } from 'react';

interface MultiComboboxOption {
    value: string;
    label: string;
}

export function MultiCombobox({
    options,
    value,
    onChange,
    placeholder = 'Select...',
    searchPlaceholder = 'Search...',
    emptyText = 'No results found.',
    className,
}: {
    options: MultiComboboxOption[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    className?: string;
}) {
    const [open, setOpen] = useState(false);

    const toggle = (val: string) => {
        if (value.includes(val)) {
            onChange(value.filter((v) => v !== val));
        } else {
            onChange([...value, val]);
        }
    };

    const selectedLabels = value.map((v) => options.find((o) => o.value === v)?.label).filter(Boolean);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className={cn('h-auto min-h-9 w-full justify-between px-3 py-2 text-sm font-normal', !value.length && 'text-neutral-500', className)}
                >
                    <div className="flex flex-wrap gap-1">
                        {selectedLabels.length === 0 ? (
                            <span>{placeholder}</span>
                        ) : (
                            selectedLabels.map((label) => (
                                <span key={label} className="inline-flex items-center gap-1 rounded-md bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">
                                    {label}
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            const opt = options.find((o) => o.label === label);
                                            if (opt) toggle(opt.value);
                                        }}
                                        className="ml-0.5 hover:text-red-500"
                                    >
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            ))
                        )}
                    </div>
                    <ChevronsUpDown className="ml-2 h-3 w-3 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[300px] p-0">
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.label}
                                    onSelect={() => toggle(option.value)}
                                >
                                    <Check className={cn('mr-2 h-3 w-3', value.includes(option.value) ? 'opacity-100' : 'opacity-0')} />
                                    {option.label}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
