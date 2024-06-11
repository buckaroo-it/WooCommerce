function BuckarooLabel({ imagePath, title }) {
  return (
    <div className="buckaroo_method_block">
      {title}
      <img src={imagePath} alt={`Payment Method ${title}`} style={{ float: 'right' }} />
    </div>
  );
}

export default BuckarooLabel;
