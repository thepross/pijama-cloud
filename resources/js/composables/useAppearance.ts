import { onMounted, ref } from 'vue';

export type Appearance = 'light' | 'dark' | 'auto';
export type ThemeType = 'default' | 'ninos' | 'jovenes' | 'adultos';
export type FontSize = 'normal' | 'large' | 'xlarge';
export type Contrast = 'normal' | 'high';

// Helper to determine day/night based on client's local hours (Night: 6 PM to 6 AM)
function isNightTime(): boolean {
    const hours = new Date().getHours();
    return hours >= 18 || hours < 6;
}

export function applyThemeClass(theme: ThemeType) {
    const el = document.documentElement;
    el.classList.remove('theme-ninos', 'theme-jovenes', 'theme-adultos');
    if (theme !== 'default') {
        el.classList.add(`theme-${theme}`);
    }
}

export function applyFontSizeClass(size: FontSize) {
    const el = document.documentElement;
    el.classList.remove('font-normal', 'font-large', 'font-xlarge');
    el.classList.add(`font-${size}`);
}

export function applyContrastClass(contrast: Contrast) {
    const el = document.documentElement;
    if (contrast === 'high') {
        el.classList.add('high-contrast');
    } else {
        el.classList.remove('high-contrast');
    }
}

export function applyAppearanceClass(value: Appearance) {
    const el = document.documentElement;
    if (value === 'auto') {
        el.classList.toggle('dark', isNightTime());
    } else {
        el.classList.toggle('dark', value === 'dark');
    }
}

export function initializeTheme() {
    const savedAppearance = (localStorage.getItem('appearance') as Appearance) || 'light';
    applyAppearanceClass(savedAppearance);

    const savedTheme = (localStorage.getItem('theme') as ThemeType) || 'default';
    applyThemeClass(savedTheme);

    const savedFontSize = (localStorage.getItem('font-size') as FontSize) || 'normal';
    applyFontSizeClass(savedFontSize);

    const savedContrast = (localStorage.getItem('contrast') as Contrast) || 'normal';
    applyContrastClass(savedContrast);
}

// Reactively exported composable
export function useAppearance() {
    const appearance = ref<Appearance>('light');
    const theme = ref<ThemeType>('default');
    const fontSize = ref<FontSize>('normal');
    const contrast = ref<Contrast>('normal');

    onMounted(() => {
        initializeTheme();

        appearance.value = (localStorage.getItem('appearance') as Appearance) || 'light';
        theme.value = (localStorage.getItem('theme') as ThemeType) || 'default';
        fontSize.value = (localStorage.getItem('font-size') as FontSize) || 'normal';
        contrast.value = (localStorage.getItem('contrast') as Contrast) || 'normal';
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);
        applyAppearanceClass(value);
    }

    function updateThemePreference(value: ThemeType) {
        theme.value = value;
        localStorage.setItem('theme', value);
        applyThemeClass(value);
    }

    function updateFontSize(value: FontSize) {
        fontSize.value = value;
        localStorage.setItem('font-size', value);
        applyFontSizeClass(value);
    }

    function updateContrast(value: Contrast) {
        contrast.value = value;
        localStorage.setItem('contrast', value);
        applyContrastClass(value);
    }

    return {
        appearance,
        theme,
        fontSize,
        contrast,
        updateAppearance,
        updateThemePreference,
        updateFontSize,
        updateContrast,
    };
}
