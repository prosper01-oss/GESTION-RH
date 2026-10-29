import Chart from 'chart.js/auto';
import { useEffect, useRef, useState } from 'react';

const isDarkMode = () => document.documentElement.classList.contains('dark');

/**
 * Graphique Chart.js qui se redessine quand le thème clair/sombre change.
 * `build(isDark)` renvoie la configuration { type, data, options }.
 */
export default function ChartCanvas({ build, deps = [], className = 'h-72' }) {
    const canvas = useRef(null);
    const [dark, setDark] = useState(isDarkMode);

    useEffect(() => {
        const observer = new MutationObserver(() => setDark(isDarkMode()));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        const chart = new Chart(canvas.current, build(dark));

        return () => chart.destroy();
    }, [dark, ...deps]);

    return (
        <div className={`relative ${className}`}>
            <canvas ref={canvas} />
        </div>
    );
}
