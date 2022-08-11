<?php
/**
 * Copyright © 2016 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magmodules\RemoveThemeRichSnippets\Plugin\Block\Product;

use Magento\Review\Block\Product\ReviewRenderer as SubjectBlock;

class ReviewRenderer
{

    /**
     * @param SubjectBlock $subject
     * @param string $result
     * @return mixed|string
     */
    public function afterGetReviewsSummaryHtml(SubjectBlock $subject, $result = '')
    {
        if ($result != ''
            && !is_null($subject->getRequest())
            && $subject->getRequest()->getFullActionName() == 'catalog_product_view'
            && $product = $subject->getProduct()) {
            $snippets_data = [
                'itemprop="aggregateRating"',
                'itemscope',
                'itemtype="http://schema.org/AggregateRating"',
                'itemprop="ratingValue"',
                'itemprop="bestRating"',
                'itemprop="reviewCount"'
            ];
            $result = str_replace($snippets_data, '', $result);
        }

        return $result;
    }
}
