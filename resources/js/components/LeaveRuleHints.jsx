/**
 * Rappels des règles de l'OMIT dans le formulaire de congé :
 * R8 (délai de dépôt, case « Urgence ») et R6 (délai du justificatif).
 */
export default function LeaveRuleHints({ leaveType, form, setForm }) {
    if (!leaveType) {
        return null;
    }

    const noticeDays = leaveType.min_notice_days ?? 0;
    const earliest = new Date();
    earliest.setHours(0, 0, 0, 0);
    earliest.setDate(earliest.getDate() + noticeDays);
    const tooSoon = noticeDays > 0 && form.start_date && new Date(`${form.start_date}T00:00:00`) < earliest;

    return (
        <div className="flex flex-col gap-2 sm:col-span-2">
            {leaveType.requires_attachment && (
                <p className="text-xs text-amber-700">
                    {leaveType.attachment_deadline_hours
                        ? `Justificatif à fournir dans les ${leaveType.attachment_deadline_hours} h suivant le premier jour : vous pouvez envoyer la demande et l'ajouter ensuite.`
                        : "Un justificatif sera demandé avant l'envoi."}
                </p>
            )}
            {noticeDays > 0 && (
                <p className={`text-xs ${tooSoon && !form.is_urgent ? 'font-medium text-red-700' : 'text-slate-500'}`}>
                    {tooSoon
                        ? `Ce congé doit être demandé au moins ${noticeDays} jours à l'avance (premier jour au plus tôt le ${earliest.toLocaleDateString('fr-FR')}). En cas d'urgence, cochez la case ci-dessous et indiquez le motif.`
                        : `À déposer au moins ${noticeDays} jours avant le premier jour, sauf urgence.`}
                </p>
            )}
            {noticeDays > 0 && (
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_urgent" checked={!!form.is_urgent} onChange={(event) => setForm({ ...form, is_urgent: event.target.checked })} />
                    Urgence (motif obligatoire)
                </label>
            )}
        </div>
    );
}
