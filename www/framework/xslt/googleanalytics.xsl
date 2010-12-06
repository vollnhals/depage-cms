<xsl:template name="googleanalytics">		
    <xsl:if test="$depage_is_live = 'true' and $tt_var_ga-Account != ''">
        <script type="text/javascript">
            var _gaq = _gaq || [];
            _gaq.push(['_setAccount', '<xsl:value-of select="$tt_var_ga-Account" />']);
            <xsl:if test="$tt_var_ga-Domain != ''">
                _gaq.push(['_setDomainName', '<xsl:value-of select="$tt_var_ga-Domain" />']);
            </xsl:if>
            _gaq.push(['_trackPageview']);

            (function() {
                var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
                ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
            })();
        </script>
    </xsl:if>
</xsl:template>
    

