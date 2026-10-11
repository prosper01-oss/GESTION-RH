const variants = {
    primary: 'bg-ink text-gold-light ring-1 ring-gold/60 hover:bg-black hover:text-white disabled:bg-stone-400 disabled:text-stone-200 disabled:ring-0',
    secondary: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:text-slate-400',
    danger: 'bg-red-600 text-white hover:brightness-110 disabled:opacity-50',
};

export default function Button({ variant = 'primary', className = '', type = 'button', ...props }) {
    return (
        <button
            type={type}
            className={`inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed ${variants[variant]} ${className}`}
            {...props}
        />
    );
}
