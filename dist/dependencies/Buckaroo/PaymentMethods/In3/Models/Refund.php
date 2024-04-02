<?php

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\In3\Models;

use WC_Buckaroo\Dependencies\Buckaroo\Models\ServiceParameter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\In3\Service\ParameterKeys\ArticleAdapter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Traits\CountableGroupKey;

class Refund extends ServiceParameter
{
    use CountableGroupKey;

    /**
     * @var array|string[]
     */
    private array $countableProperties = ['articles'];

    /**
     * @var string
     */
    protected string $merchantImageUrl;

    /**
     * @var string
     */
    protected string $summaryImageUrl;

    /**
     * @var array
     */
    protected array $articles = [];

    /**
     * @var array|\string[][]
     */
    protected array $groupData = [
        'articles' => [
            'groupType' => 'Article',
        ],
    ];

    /**
     * @param array|null $articles
     * @return array
     */
    public function articles(?array $articles = null)
    {
        if (is_array($articles))
        {
            foreach ($articles as $article)
            {
                $this->articles[] = new ArticleAdapter(new Article($article));
            }
        }

        return $this->articles;
    }
}
