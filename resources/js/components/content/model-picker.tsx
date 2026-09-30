import { Check, ChevronsUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { ModelOption } from '@/types';

/**
 * Picks a model from the synced catalog. An empty value means "use the
 * default", which is shown as the first option.
 */
export function ModelPicker({
    id,
    value,
    options,
    defaultLabel,
    onChange,
    warnFor,
}: {
    id?: string;
    value: string | null;
    options: ModelOption[];
    defaultLabel: string;
    onChange: (value: string | null) => void;
    /** Input types the chat holds; models that cannot read them get a warning. */
    warnFor?: string[];
}) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => option.id === value);

    const missing = (option: ModelOption) =>
        (warnFor ?? []).filter((kind) => !option.input.includes(kind));

    const sorted = useMemo(
        () => [...options].sort((a, b) => a.name.localeCompare(b.name)),
        [options],
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full justify-between font-normal"
                >
                    <span className="truncate">
                        {value ? (selected?.name ?? value) : defaultLabel}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-(--radix-popover-trigger-width) min-w-80 p-0">
                <Command>
                    <CommandInput placeholder="Search models..." />
                    <CommandList>
                        <CommandEmpty>No model found.</CommandEmpty>
                        <CommandGroup>
                            <CommandItem
                                value={`__default ${defaultLabel}`}
                                onSelect={() => {
                                    onChange(null);
                                    setOpen(false);
                                }}
                            >
                                <Check
                                    className={cn(
                                        'size-4',
                                        value ? 'opacity-0' : 'opacity-100',
                                    )}
                                />
                                {defaultLabel}
                            </CommandItem>
                            {sorted.map((option) => {
                                const gaps = missing(option);

                                return (
                                    <CommandItem
                                        key={option.id}
                                        value={`${option.name} ${option.id}`}
                                        onSelect={() => {
                                            onChange(option.id);
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4',
                                                value === option.id
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate">
                                                {option.name}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {option.id} · reads{' '}
                                                {option.input.join(', ')}
                                            </span>
                                        </div>
                                        {gaps.length > 0 && (
                                            <Badge
                                                variant="outline"
                                                className="shrink-0 border-amber-500/50 text-amber-600 dark:text-amber-400"
                                            >
                                                no {gaps.join(', ')}
                                            </Badge>
                                        )}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
