<?php

namespace PHPSTORM_META {
    expectedArguments(
        \Daif\ChromePdfBundle\Builder\AbstractBuilder::fileName(),
        1,
        \Symfony\Component\HttpFoundation\HeaderUtils::DISPOSITION_INLINE,
        \Symfony\Component\HttpFoundation\HeaderUtils::DISPOSITION_ATTACHMENT,
    );
}
