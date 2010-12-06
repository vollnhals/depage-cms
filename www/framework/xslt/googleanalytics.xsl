<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE xsl:stylesheet [ 
    <!ENTITY nbsp "&#160;">
]>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:rpc="http://cms.depagecms.net/ns/rpc" xmlns:db="http://cms.depagecms.net/ns/database" xmlns:proj="http://cms.depagecms.net/ns/project" xmlns:pg="http://cms.depagecms.net/ns/page" xmlns:sec="http://cms.depagecms.net/ns/section" xmlns:edit="http://cms.depagecms.net/ns/edit" xmlns:backup="http://cms.depagecms.net/ns/backup" version="1.0" extension-element-prefixes="xsl rpc db proj pg sec edit backup ">
<!-- {{{ Google Analytics -->
<xsl:template name="googleanalytics">		
    <xsl:if test="$tt_var_ga-Account != ''">
        <script type="text/javascript">
            var _gaq = _gaq || [];
            _gaq.push(['_setAccount', '<xsl:value-of select="$tt_var_ga-Account" />']);
            <xsl:if test="$tt_var_ga-Domain != ''">
                _gaq.push(['_setDomainName', '<xsl:value-of select="$tt_var_ga-Domain" />']);
            </xsl:if>
            _gaq.push(['_trackPageview']);

            <xsl:if test="$depage_is_live = 'true'">
                (function() {
                    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
                    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
                    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
                })();
            </xsl:if>
        </script>
    </xsl:if>
</xsl:template>
<!-- }}} -->
    
    <!-- vim:set ft=xml sw=4 sts=4 fdm=marker : -->
</xsl:stylesheet>
