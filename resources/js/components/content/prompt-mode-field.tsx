import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import type { PromptMode } from '@/types';

/**
 * Whether a project or chat system prompt adds to the one above it or
 * replaces it. Adding is the default.
 */
export function PromptModeField({
    name,
    value,
    onChange,
    above,
}: {
    name: string;
    value: PromptMode;
    onChange?: (value: PromptMode) => void;
    above: string;
}) {
    return (
        <RadioGroup
            name={name}
            defaultValue={onChange ? undefined : value}
            value={onChange ? value : undefined}
            onValueChange={(next) => onChange?.(next as PromptMode)}
            className="flex flex-wrap gap-4"
        >
            <div className="flex items-center gap-2">
                <RadioGroupItem value="append" id={`${name}-append`} />
                <Label htmlFor={`${name}-append`} className="font-normal">
                    Add to {above}
                </Label>
            </div>
            <div className="flex items-center gap-2">
                <RadioGroupItem value="replace" id={`${name}-replace`} />
                <Label htmlFor={`${name}-replace`} className="font-normal">
                    Replace {above}
                </Label>
            </div>
        </RadioGroup>
    );
}
