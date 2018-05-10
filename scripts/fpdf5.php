<?php
// figuras geometricas e imagens
require('app.util/pdf/fpdf.php');

class Documento1 extends FPDF
{
    /**
     * Define o método para imprimir o cabeçalho
     */
    function Header()
    {
        $this->Image('images/banner.jpg', 20, 10);
        $this->Ln(70);
        $this->SetFont('Arial','B',16);
        $this->Cell(520,20,'Título do documento',1,0,'C');
        $this->Ln(40);
    }
    
    /**
     * Define o método para imprimir o rodapé
     */
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','B',8);
        $this->Cell(0,10,'Página '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new Documento1('P', 'pt', 'A4');
$pdf->AddPage();
$pdf->AliasNbPages();
$pdf->Write(20, str_repeat('teste ', 1000));
$pdf->Output();
?>
