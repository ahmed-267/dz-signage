<?php

namespace App\Enums;

enum TemplateTheme: string
{
    case Minimal = 'minimal';
    case Modern = 'modern';
    case Bold = 'bold';
    case Corporate = 'corporate';
    case Elegant = 'elegant';
    case Clean = 'clean';
    case Dark = 'dark';
    case Light = 'light';
    case Colourful = 'colourful';
    case Islamic = 'islamic';
    case Blank = 'blank';

    public function label(): string
    {
        return match ($this) {
            self::Minimal => 'Minimal',
            self::Modern => 'Modern',
            self::Bold => 'Bold',
            self::Corporate => 'Corporate',
            self::Elegant => 'Elegant',
            self::Clean => 'Clean',
            self::Dark => 'Dark',
            self::Light => 'Light',
            self::Colourful => 'Colourful',
            self::Islamic => 'Islamic',
            self::Blank => 'Blank',
        };
    }

    public function defaultBackground(): string
    {
        return match ($this) {
            self::Minimal => '#F8F9FA',
            self::Modern => '#EEF2FF',
            self::Bold => '#111827',
            self::Corporate => '#F1F5F9',
            self::Elegant => '#FAF7F2',
            self::Clean => '#FFFFFF',
            self::Dark => '#0F172A',
            self::Light => '#FCFCFC',
            self::Colourful => '#FFF7ED',
            self::Islamic => '#ECFDF5',
            self::Blank => '#FFFFFF',
        };
    }
}
