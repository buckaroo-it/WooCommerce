function BuckarooLabel({ imagePath, title, testModeLabel }) {
    return (
        <div className="buckaroo_method_block">
            <span className="buckaroo_method_block_title">{title}</span>
            {testModeLabel && <span className="buckaroo-testmode-badge">{testModeLabel}</span>}
            <img className="buckaroo_method_block_img" src={imagePath} alt={`Payment Method ${title}`} />
        </div>
    );
}

export default BuckarooLabel;
