<?php

namespace PrestaShop\PSTAF\ShopCapability\Helper;

use PrestaShop\PSTAF\ShopCapability\BackOfficePagination;

class BackOfficePaginator
{
    private $pagination;
    private $settings;

    public function __construct(
        BackOfficePagination $pagination,
        array $settings
    )
    {
        $this->pagination = $pagination;
        $this->settings = $settings;
    }

    public function getCurrentPageNumber()
    {
        $browser = $this->pagination->getShop()->getBrowser();

        return (int) $browser->getAttribute($this->settings['container_selector'].' ul.pagination li.active a', 'data-page');
    }

    public function getNextPageNumber()
    {
        $next = $this->getCurrentPageNumber() + 1;

        $selector = $this->settings['container_selector'].' ul.pagination li a[data-page="'.$next.'"]';

        return $this->pagination->getShop()->getBrowser()->hasVisible($selector);
    }

    public function getLastPageNumber()
    {
        $browser = $this->pagination->getShop()->getBrowser();

        try {
            $elem = $browser->find($this->settings['container_selector'].' ul.pagination li:last-child a', ['wait' => false]);

            return (int) $elem->getAttribute('data-page');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function gotoPage($n)
    {
        $this->pagination->getShop()->getBrowser()
        ->clickFirst($this->settings['container_selector'].' ul.pagination li a[data-page="'.$n.'"]');
    }

    private function getHeaderName($header)
    {
        if (is_string($header))
            return $header;
        else
            return $header['name'];
    }

    private function getTDValue($header, $td)
    {
        $type = is_string($header) ? 'verbatim' : $header['type'];
        $m = [];

        if ($type === 'verbatim') {
            return $td->getText();
        } elseif (preg_match('/^switch:(.+)$/', $type, $m)) {
            return $td->find('.'.$m[1])->isDisplayed();
        } elseif (preg_match('/^i18n:(.+)$/', $type, $m)) {
            $value = $this->pagination->i18nParse($td->getText(), $m[1]);

            return $value;
        } else {
            return null;
        }
    }

    public function scrape()
    {
        $rows = [];

        $ths = $this->pagination->getShop()->getBrowser()->find(
            $this->settings['container_selector'].' '.$this->settings['table_selector'].' thead tr th',
            ['unique' => false]
        );

        foreach ($this->pagination->getShop()->getBrowser()->find(
            $this->settings['container_selector'].' '.$this->settings['table_selector'].' tbody tr',
            ['unique' => false]
        ) as $tr)
        {
            $row = [];
            $n = 0;
            foreach ($tr->all('td') as $i => $td) {
                /**
				 * Ignore cells corresponding to columns without text
				 * this is useful because an extra column appears when there are bulk actions
				 */
                if (!$ths[$i]->getText())
                    continue;

                if (!empty($this->settings['columns'][$n])) {
                    $header = $this->settings['columns'][$n];
                    $row[$this->getHeaderName($header)] = $this->getTDValue($header, $td);
                }

                $n++;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function scrapeAll()
    {
        try {
            $max_page = $this->getLastPageNumber();
        } catch (\Exception $e) {
            $max_page = null;
        }

        if ($max_page === null)
            return $this->scrape();

        $rows = [];
        for ($p = 1; $p <= $max_page; $p++) {
            $this->gotoPage($p);
            $data = $this->scrape();
            $rows = array_merge($rows, $data);
        }

        return $rows;
    }

}
