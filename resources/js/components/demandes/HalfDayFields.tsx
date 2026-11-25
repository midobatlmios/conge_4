import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface HalfDayFieldsProps {
    start: string; // YYYY-MM-DD
    end: string;
    startHalf: boolean; // starts in the afternoon
    endHalf: boolean; // ends at noon
    onChange: (halves: { start_half: boolean; end_half: boolean }) => void;
}

type SingleDay = 'full' | 'morning' | 'afternoon';

/**
 * Half-day choice for a leave request. One day: whole day, morning or afternoon.
 * Several days: start in the afternoon and/or end at noon.
 */
export default function HalfDayFields({ start, end, startHalf, endHalf, onChange }: HalfDayFieldsProps) {
    if (!start || !end) return null;

    if (start === end) {
        const value: SingleDay = endHalf ? 'morning' : startHalf ? 'afternoon' : 'full';
        return (
            <div className="space-y-1">
                <Label htmlFor="half_day">Durée</Label>
                <Select
                    value={value}
                    onValueChange={(choice: SingleDay) => onChange({ start_half: choice === 'afternoon', end_half: choice === 'morning' })}
                >
                    <SelectTrigger id="half_day" className="sm:w-64">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="full">Journée entière</SelectItem>
                        <SelectItem value="morning">Matin (demi-journée)</SelectItem>
                        <SelectItem value="afternoon">Après-midi (demi-journée)</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap gap-x-6 gap-y-2">
            <div className="flex items-center gap-2">
                <Checkbox
                    id="start_half"
                    checked={startHalf}
                    onCheckedChange={(checked) => onChange({ start_half: checked === true, end_half: endHalf })}
                />
                <Label htmlFor="start_half" className="font-normal">
                    Commencer l'après-midi
                </Label>
            </div>
            <div className="flex items-center gap-2">
                <Checkbox
                    id="end_half"
                    checked={endHalf}
                    onCheckedChange={(checked) => onChange({ start_half: startHalf, end_half: checked === true })}
                />
                <Label htmlFor="end_half" className="font-normal">
                    Terminer à midi
                </Label>
            </div>
        </div>
    );
}
