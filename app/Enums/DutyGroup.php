<?php

namespace App\Enums;

/**
 * FEM 1.001 crane classification group (A1 light ... A8 very heavy).
 * The reference drawing is FEM A5. The group is stored and shown on the
 * drawing only; it does not change any dimension in this tool.
 */
enum DutyGroup: string
{
    case A3 = 'A3';
    case A4 = 'A4';
    case A5 = 'A5';
    case A6 = 'A6';
}
