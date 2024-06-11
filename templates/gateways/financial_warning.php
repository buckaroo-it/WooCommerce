<?php if ( $this->can_show_financial_warining() ) {
	?>
	<div style="display:block; font-size:.8rem; clear:both;">
		<?php
		echo esc_html(
			'Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van ' . $this->title . '. Door verder te gaan, accepteer je de Algemene Voorwaarden en bevestig je dat je de Privacyverklaring en Cookieverklaring hebt gelezen.'
		);
		?>
	</div>
	<?php
}?>