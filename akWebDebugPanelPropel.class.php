<?php

class akWebDebugPanelPropel extends sfWebDebugPanelPropel
{
    protected $isWarningCallable;

    protected $sqlLogs;

    /**
     * Constructor.
     *
     * @param sfWebDebug $webDebug The web debug toolbar instance
     */
    public function __construct(sfWebDebug $webDebug)
    {
        parent::__construct($webDebug);
        $this->isWarningCallable = array($this, 'isWarningExplain');
    }

    /**
     * Gets the title of the panel.
     *
     * @return string The panel title
     */
    public function getPanelTitle()
    {
        return parent::getPanelTitle() . ' with explain';
    }

    /**
     * Gets the panel HTML content.
     *
     * @return string The panel HTML content
     */
    public function getPanelContent()
    {
        $content = parent::getPanelContent();
        $query_count = substr_count($content, 'sfWebDebugDatabaseQuery');

        $config = $this->getPropelConfiguration();
        $query_count_limit = $config->getParameter('debugpdo.logging.query_count', 0);

        if ($query_count_limit > 0 && $query_count >= $query_count_limit && $this->getStatus() > sfLogger::NOTICE) {
            $this->setStatus(sfLogger::NOTICE);
        }

        return preg_replace_callback(
            '!<li(?: class="(.*?)")?>\s*<p class="sfWebDebugDatabaseQuery">(.*?)</p>!',
            array($this, 'appendExplain'),
            $content
        );
    }

    /**
     * appendExplain
     */
    public function appendExplain($matches)
    {
        list($null, $warning, $query) = $matches;
        $raw_query = htmlspecialchars_decode(strip_tags($query), ENT_QUOTES);

        if (!$this->isIgnoreQuery($raw_query)) {
            try {
                $explain = Propel::getConnection(null, Propel::CONNECTION_READ)
                    ->query('EXPLAIN ' . $raw_query)
                    ->fetchAll(PDO::FETCH_ASSOC);

                if (call_user_func($this->isWarningCallable, $explain)) {
                    $warning = 'sfWebDebugWarning';
                    if ($this->getStatus() > sfLogger::NOTICE) {
                        $this->setStatus(sfLogger::NOTICE);
                    }
                }

                $query .= '&nbsp;' . $this->getToggleableExplain($explain);
            } catch (PDOException $e) {
                // SQLを直接実行した場合
                // SQLログがパラメータをバインドしない状態で保存されている
            }
        }

        return sprintf(
            '<li class="%s"><p class="sfWebDebugDatabaseQuery">%s<p>',
            $warning,
            $query
        );
    }

    /**
     * getToggleableExplain
     *
     * @param  array $explain
     * @return string
     */
    public function getToggleableExplain($explain)
    {
        static $i = 1;

        $element = get_class($this) . 'Explain' . $i++;

        $html  = $this->getToggler($element, 'Toggle explain sql');
        $html .= '<table class="sfWebDebugLogs" id="'.$element.'" style="display:none;width:500px;">';
        $html .= '<tbody>';

        $html .= '<tr>';
        foreach (array_keys($explain[0]) as $k) {
            $html .= '<th>' . $k . '</th>';
        }
        $html .= '</tr>';

        foreach ($explain as $row) {
            $html .= '<tr>';
            foreach ($row as $k => $v) {
                $html .= sprintf(
                    '<td class="%s" style="%s">%s</td>',
                    in_array($k, array('type', 'Extra')) ? 'sfWebDebugLogType' : '',
                    is_numeric($v) ? 'text-align:right;' : '',
                    $v
                );
            }
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= "</table>\n";

        return $html;
    }

    protected function isWarningExplain($explain)
    {
        $config = $this->getPropelConfiguration();
        $cond = $config->getParameter('debugpdo.logging.explain', array());

        foreach ($explain as $row) {
            foreach ($cond as $field => $pattern) {
                if (preg_match($pattern, $row[$field])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function setIsWarningCallable($callable)
    {
        if (!is_callable($callable)) {
            throw new Exception('Callback function must be a valid callback using is_callable().');
        }

        $this->isWarningCallable = $callable;
    }

    /**
     * Builds the sql logs and returns them as an array.
     *
     * @return array
     */
    protected function getSqlLogs()
    {
        if ($this->sqlLogs === null) {
            $this->sqlLogs = parent::getSqlLogs();
        }

        return $this->sqlLogs;
    }

    /**
     * isIgnoreQuery
     *
     * @param string $query
     * @return bool
     */
    protected function isIgnoreQuery($query)
    {
        return strpos(strtolower($query), 'set names') === 0;
    }
}
