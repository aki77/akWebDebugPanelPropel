<?php

class akWebDebugPanelPropel extends sfWebDebugPanel
{
    protected $panel;

    protected $isWarningCallable;

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
     * Gets the text for the link.
     *
     * @return string The link text
     */
    public function getTitle()
    {
        return $this->panel->getTitle();
    }

    /**
     * Gets the title of the panel.
     *
     * @return string The panel title
     */
    public function getPanelTitle()
    {
        return $this->panel->getPanelTitle() . ' with explain';
    }

    /**
     * Gets the panel HTML content.
     *
     * @return string The panel HTML content
     */
    public function getPanelContent()
    {
        $content = $this->panel->getPanelContent();
        $query_count = substr_count($content, 'sfWebDebugDatabaseQuery');

        $config = Propel::getConfiguration(PropelConfiguration::TYPE_OBJECT);
        $query_count_limit = $config->getParameter('debugpdo.logging.query_count', 0);

        if ($query_count_limit > 0 && $query_count >= $query_count_limit && $this->getStatus() > sfLogger::NOTICE) {
            $this->setStatus(sfLogger::NOTICE);
        }

        return preg_replace_callback(
            '!<li class="(.*?)">\s*<p class="sfWebDebugDatabaseQuery">(.*?)</p>!',
            array($this, 'appendExplain'),
            $content
        );
    }

    public function setPanel(sfWebDebugPanelPropel $panel)
    {
        $this->panel = $panel;
    }

    public function appendExplain($matches)
    {
        list($null, $warning, $query) = $matches;
        $raw_query = strip_tags(htmlspecialchars_decode($query, ENT_QUOTES));

        if (strpos($raw_query, 'SET NAMES') !== 0) {
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
        }

        return sprintf(
            '<li class="%s"><p class="sfWebDebugDatabaseQuery">%s<p>',
            $warning,
            $query
        );
    }

    /**
     * getToggleableDebugStack
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
        foreach ($explain as $v) {
            if (
                in_array($v['type'], array('index', 'ALL')) ||
                preg_match('!filesort|temporary!', $v['Extra'])
            ) {
                return true;
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
}
