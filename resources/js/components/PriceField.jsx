import { chiffresSeulement, montant } from '../services/pharma';
import Field from './Field';

/**
 * Champ de prix en Ariary : seuls les chiffres sont acceptés, l'affichage est groupé par milliers.
 * Avec `decimal`, un séparateur décimal est accepté et la saisie n'est pas regroupée.
 * onChange reçoit la chaîne saisie (« 12500 »).
 */
export default function PriceField({ value, onChange, decimal = false, ...props }) {
    if (decimal) {
        return <Field inputMode="decimal" placeholder="0" value={value} onChange={(event) => onChange(event.target.value.replace(',', '.').replace(/[^\d.]/g, ''))} {...props} />;
    }

    return <Field inputMode="numeric" placeholder="0" value={montant(value)} onChange={(event) => onChange(chiffresSeulement(event.target.value))} {...props} />;
}
