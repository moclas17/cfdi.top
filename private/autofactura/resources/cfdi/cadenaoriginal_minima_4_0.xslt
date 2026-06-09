<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:cfdi="http://www.sat.gob.mx/cfd/4">
    <xsl:output method="text" encoding="UTF-8" omit-xml-declaration="yes"/>

    <xsl:template match="/">|<xsl:apply-templates select="/cfdi:Comprobante"/>||</xsl:template>

    <xsl:template match="cfdi:Comprobante">
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Version"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@Serie"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@Folio"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Fecha"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@FormaPago"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@NoCertificado"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@CondicionesDePago"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@SubTotal"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@Descuento"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Moneda"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@TipoCambio"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Total"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@TipoDeComprobante"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Exportacion"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@MetodoPago"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@LugarExpedicion"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@Confirmacion"/></xsl:call-template>
        <xsl:apply-templates select="cfdi:Emisor"/>
        <xsl:apply-templates select="cfdi:Receptor"/>
        <xsl:apply-templates select="cfdi:Conceptos"/>
        <xsl:apply-templates select="cfdi:Impuestos"/>
    </xsl:template>

    <xsl:template match="cfdi:Emisor">
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Rfc"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Nombre"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@RegimenFiscal"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@FacAtrAdquirente"/></xsl:call-template>
    </xsl:template>

    <xsl:template match="cfdi:Receptor">
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Rfc"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Nombre"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@DomicilioFiscalReceptor"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@ResidenciaFiscal"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@NumRegIdTrib"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@RegimenFiscalReceptor"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@UsoCFDI"/></xsl:call-template>
    </xsl:template>

    <xsl:template match="cfdi:Conceptos">
        <xsl:apply-templates select="cfdi:Concepto"/>
    </xsl:template>

    <xsl:template match="cfdi:Concepto">
        <xsl:call-template name="req"><xsl:with-param name="v" select="@ClaveProdServ"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@NoIdentificacion"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Cantidad"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@ClaveUnidad"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@Unidad"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Descripcion"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@ValorUnitario"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@Importe"/></xsl:call-template>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@Descuento"/></xsl:call-template>
        <xsl:call-template name="req"><xsl:with-param name="v" select="@ObjetoImp"/></xsl:call-template>
        <xsl:for-each select="cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado">
            <xsl:call-template name="req"><xsl:with-param name="v" select="@Base"/></xsl:call-template>
            <xsl:call-template name="req"><xsl:with-param name="v" select="@Impuesto"/></xsl:call-template>
            <xsl:call-template name="req"><xsl:with-param name="v" select="@TipoFactor"/></xsl:call-template>
            <xsl:call-template name="opt"><xsl:with-param name="v" select="@TasaOCuota"/></xsl:call-template>
            <xsl:call-template name="opt"><xsl:with-param name="v" select="@Importe"/></xsl:call-template>
        </xsl:for-each>
        <xsl:for-each select="cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion">
            <xsl:call-template name="req"><xsl:with-param name="v" select="@Base"/></xsl:call-template>
            <xsl:call-template name="req"><xsl:with-param name="v" select="@Impuesto"/></xsl:call-template>
            <xsl:call-template name="req"><xsl:with-param name="v" select="@TipoFactor"/></xsl:call-template>
            <xsl:call-template name="req"><xsl:with-param name="v" select="@TasaOCuota"/></xsl:call-template>
            <xsl:call-template name="req"><xsl:with-param name="v" select="@Importe"/></xsl:call-template>
        </xsl:for-each>
    </xsl:template>

    <xsl:template match="cfdi:Impuestos">
        <xsl:for-each select="cfdi:Retenciones/cfdi:Retencion">
            <xsl:call-template name="req"><xsl:with-param name="v" select="@Impuesto"/></xsl:call-template>
            <xsl:call-template name="req"><xsl:with-param name="v" select="@Importe"/></xsl:call-template>
        </xsl:for-each>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@TotalImpuestosRetenidos"/></xsl:call-template>
        <xsl:for-each select="cfdi:Traslados/cfdi:Traslado">
            <xsl:call-template name="req"><xsl:with-param name="v" select="@Base"/></xsl:call-template>
            <xsl:call-template name="req"><xsl:with-param name="v" select="@Impuesto"/></xsl:call-template>
            <xsl:call-template name="req"><xsl:with-param name="v" select="@TipoFactor"/></xsl:call-template>
            <xsl:call-template name="opt"><xsl:with-param name="v" select="@TasaOCuota"/></xsl:call-template>
            <xsl:call-template name="opt"><xsl:with-param name="v" select="@Importe"/></xsl:call-template>
        </xsl:for-each>
        <xsl:call-template name="opt"><xsl:with-param name="v" select="@TotalImpuestosTrasladados"/></xsl:call-template>
    </xsl:template>

    <xsl:template name="req">
        <xsl:param name="v"/>
        <xsl:text>|</xsl:text><xsl:value-of select="normalize-space($v)"/>
    </xsl:template>

    <xsl:template name="opt">
        <xsl:param name="v"/>
        <xsl:if test="string-length(normalize-space($v)) &gt; 0">
            <xsl:text>|</xsl:text><xsl:value-of select="normalize-space($v)"/>
        </xsl:if>
    </xsl:template>
</xsl:stylesheet>
