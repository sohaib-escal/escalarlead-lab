import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button, Modal, Select } from './Ui';

/**
 * "What if we changed one thing?" — the fastest path to a systematic test.
 * Everything else on the creative is kept.
 */
export default function VariationModal({ creative, categories, open, onClose }) {
    const [categoryId, setCategoryId] = useState('');
    const [valueId, setValueId] = useState('');

    const current = useMemo(() => {
        const map = {};
        (creative?.persona ?? []).forEach((chip) => {
            map[chip.category_slug] = chip.label;
        });
        return map;
    }, [creative]);

    const category = categories.find((c) => String(c.id) === String(categoryId));

    const submit = () => {
        router.post(
            `/creatives/${creative.id}/duplicate`,
            { variations: [{ parameter_category_id: categoryId, parameter_value_id: valueId }] },
            { onSuccess: onClose },
        );
    };

    if (!creative) return null;

    return (
        <Modal open={open} onClose={onClose} title={`Variation de ${creative.reference}`}>
            <p className="mb-3 text-xs text-slate-500">
                On garde la copie, le visuel et le reste du ciblage. On ne change qu&apos;une seule variable — c&apos;est ce qui rend
                le test lisible.
            </p>

            <div className="grid gap-3 sm:grid-cols-2">
                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-slate-600">Changer…</span>
                    <Select
                        value={categoryId}
                        onChange={(e) => {
                            setCategoryId(e.target.value);
                            setValueId('');
                        }}
                    >
                        <option value="">Choisir une dimension</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                                {current[c.slug] ? ` (actuellement : ${current[c.slug]})` : ''}
                            </option>
                        ))}
                    </Select>
                </label>

                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-slate-600">…en</span>
                    <Select value={valueId} onChange={(e) => setValueId(e.target.value)} disabled={!category}>
                        <option value="">Choisir une valeur</option>
                        {(category?.values ?? [])
                            .filter((v) => v.label !== current[category?.slug])
                            .map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.label}
                                </option>
                            ))}
                    </Select>
                </label>
            </div>

            {category && valueId && (
                <div className="mt-4 rounded-xl bg-slate-50 p-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Ce qui change</p>
                    <p className="mt-1 text-sm text-slate-800">
                        <span className="font-medium">{category.name}</span> : {current[category.slug] ?? '—'}{' '}
                        <span className="text-teal-700">→</span>{' '}
                        <span className="font-medium text-teal-800">
                            {category.values.find((v) => String(v.id) === String(valueId))?.label}
                        </span>
                    </p>
                    <p className="mt-1 text-[11px] text-slate-500">
                        Tout le reste est inchangé : copy, visuel, canaux et le reste du ciblage.
                    </p>
                </div>
            )}

            <div className="mt-4 flex justify-end gap-2">
                <Button variant="secondary" onClick={onClose}>
                    Annuler
                </Button>
                <Button onClick={submit} disabled={!categoryId || !valueId}>
                    Créer la variation
                </Button>
            </div>
        </Modal>
    );
}
