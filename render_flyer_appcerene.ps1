Add-Type -AssemblyName System.Drawing

$width = 1080
$height = 1350
$outPath = Join-Path $PSScriptRoot 'flyer_sistema_appcerene.png'
$logoPath = Join-Path $PSScriptRoot 'logo.png'

$bmp = New-Object System.Drawing.Bitmap $width, $height
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

function Brush($hex) {
    return New-Object System.Drawing.SolidBrush ([System.Drawing.ColorTranslator]::FromHtml($hex))
}

function PenColor($hex, $size) {
    return New-Object System.Drawing.Pen ([System.Drawing.ColorTranslator]::FromHtml($hex)), $size
}

function RoundRect($x, $y, $w, $h, $r) {
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $d = $r * 2
    $path.AddArc($x, $y, $d, $d, 180, 90)
    $path.AddArc($x + $w - $d, $y, $d, $d, 270, 90)
    $path.AddArc($x + $w - $d, $y + $h - $d, $d, $d, 0, 90)
    $path.AddArc($x, $y + $h - $d, $d, $d, 90, 90)
    $path.CloseFigure()
    return $path
}

function DrawText($text, $font, $brush, $x, $y, $w, $h, $align = 'Near') {
    $fmt = New-Object System.Drawing.StringFormat
    $fmt.Alignment = [System.Drawing.StringAlignment]::$align
    $fmt.LineAlignment = [System.Drawing.StringAlignment]::Near
    $fmt.Trimming = [System.Drawing.StringTrimming]::Word
    $rect = New-Object System.Drawing.RectangleF($x, $y, $w, $h)
    $g.DrawString($text, $font, $brush, $rect, $fmt)
    $fmt.Dispose()
}

$bg = Brush '#F4F8FB'
$dark = Brush '#0F2D3A'
$blue = Brush '#1988D8'
$teal = Brush '#36B7A6'
$mint = Brush '#E7F8F4'
$white = Brush '#FFFFFF'
$muted = Brush '#58707B'
$text = Brush '#17313B'
$lightText = Brush '#DCECF1'
$accent = Brush '#F2B84B'

$g.Clear([System.Drawing.ColorTranslator]::FromHtml('#F4F8FB'))

$headerPath = RoundRect -x 42 -y 38 -w 996 -h 470 -r 42
$g.FillPath($dark, $headerPath)

$circle1 = Brush '#164456'
$circle2 = Brush '#1B5E77'
$g.FillEllipse($circle1, 785, -40, 330, 330)
$g.FillEllipse($circle2, 720, 260, 230, 230)
$g.FillEllipse($teal, 72, 392, 88, 88)

if (Test-Path $logoPath) {
    $logo = [System.Drawing.Image]::FromFile($logoPath)
    $g.DrawImage($logo, 78, 78, 300, 61)
    $logo.Dispose()
}

$fontTag = New-Object System.Drawing.Font('Segoe UI Semibold', 22)
$fontTitle = New-Object System.Drawing.Font('Segoe UI', 54, [System.Drawing.FontStyle]::Bold)
$fontSubtitle = New-Object System.Drawing.Font('Segoe UI', 24)
$fontBody = New-Object System.Drawing.Font('Segoe UI', 23)
$fontSmall = New-Object System.Drawing.Font('Segoe UI', 19)
$fontCardTitle = New-Object System.Drawing.Font('Segoe UI', 28, [System.Drawing.FontStyle]::Bold)
$fontCard = New-Object System.Drawing.Font('Segoe UI', 20)
$fontCta = New-Object System.Drawing.Font('Segoe UI', 26, [System.Drawing.FontStyle]::Bold)

DrawText 'SISTEMA DE GESTION INTEGRAL' $fontTag $accent 78 162 760 40
DrawText 'Sistema AppCerene' $fontTitle $white 76 235 720 74
DrawText 'Gestion integral de agenda, citas, tickets, pagos y seguimiento en una sola plataforma.' $fontSubtitle $lightText 80 340 690 86

$panel = RoundRect -x 84 -y 548 -w 912 -h 178 -r 34
$g.FillPath($white, $panel)
DrawText 'Coordina citas, tickets, pagos y seguimiento diario.' $fontBody $text 122 596 835 42

$pill1 = RoundRect -x 122 -y 668 -w 225 -h 40 -r 20
$pill2 = RoundRect -x 376 -y 668 -w 245 -h 40 -r 20
$pill3 = RoundRect -x 650 -y 668 -w 245 -h 40 -r 20
$g.FillPath($mint, $pill1)
$g.FillPath($mint, $pill2)
$g.FillPath($mint, $pill3)
DrawText 'Agenda clara' $fontSmall $teal 145 675 180 28 'Center'
DrawText 'Control de pagos' $fontSmall $teal 392 675 215 28 'Center'
DrawText 'Seguimiento total' $fontSmall $teal 665 675 220 28 'Center'

$cards = @(
    @{ X = 84; Y = 770; Num = '01'; Title = 'Agenda'; Body = 'Visualiza horarios y evita empalmes.' },
    @{ X = 552; Y = 770; Num = '02'; Title = 'Tickets'; Body = 'Registra atenciones y consulta detalles.' },
    @{ X = 84; Y = 980; Num = '03'; Title = 'Pagos'; Body = 'Revisa pagos, tickets y adeudos.' },
    @{ X = 552; Y = 980; Num = '04'; Title = 'Prospectos'; Body = 'Da seguimiento a mensajes e historial.' }
)

foreach ($card in $cards) {
    $cardPath = RoundRect -x $card.X -y $card.Y -w 444 -h 178 -r 28
    $g.FillPath($white, $cardPath)
    $iconPath = RoundRect -x ($card.X + 28) -y ($card.Y + 30) -w 58 -h 58 -r 17
    $g.FillPath($blue, $iconPath)
    DrawText $card.Num (New-Object System.Drawing.Font('Segoe UI', 18, [System.Drawing.FontStyle]::Bold)) $white ($card.X + 35) ($card.Y + 45) 44 30 'Center'
    DrawText $card.Title $fontCardTitle $text ($card.X + 105) ($card.Y + 25) 300 42
    DrawText $card.Body $fontCard $muted ($card.X + 105) ($card.Y + 78) 290 70
}

$ctaPath = RoundRect -x 84 -y 1206 -w 912 -h 96 -r 32
$g.FillPath($teal, $ctaPath)
DrawText 'Moderniza tu administracion' $fontCta $white 124 1220 610 36
DrawText 'Solicita una demostracion del sistema AppCerene' $fontSmall $white 126 1262 620 28

$badge = RoundRect -x 772 -y 1230 -w 178 -h 48 -r 24
$g.FillPath($dark, $badge)
DrawText 'APP CERENE' $fontSmall $white 792 1241 138 28 'Center'

$footerFont = New-Object System.Drawing.Font('Segoe UI', 18)
DrawText 'Gestion de agenda | citas | tickets | pagos | adeudos | seguimiento' $footerFont $muted 84 1305 912 30 'Center'

$bmp.Save($outPath, [System.Drawing.Imaging.ImageFormat]::Png)

$fontTag.Dispose(); $fontTitle.Dispose(); $fontSubtitle.Dispose(); $fontBody.Dispose(); $fontSmall.Dispose(); $fontCardTitle.Dispose(); $fontCard.Dispose(); $fontCta.Dispose(); $footerFont.Dispose()
$bg.Dispose(); $dark.Dispose(); $blue.Dispose(); $teal.Dispose(); $mint.Dispose(); $white.Dispose(); $muted.Dispose(); $text.Dispose(); $lightText.Dispose(); $accent.Dispose(); $circle1.Dispose(); $circle2.Dispose()
$g.Dispose(); $bmp.Dispose()

"Flyer generado: $outPath"
