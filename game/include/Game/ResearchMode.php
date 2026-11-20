<?php

declare(strict_types=1);

namespace Game;

/** research.mode: which queue a research row belongs to (SmithyCtrl, AcademyCtrl, Automation::processResearchTask). */
final class ResearchMode
{
    /** Smithy upgrade of unit nr 1-8 (smithy.uN + 1). */
    public const SMITHY = 0;
    /** Academy research of unit nr 2-9 (tdata.uN = 1). */
    public const ACADEMY = 1;
}
