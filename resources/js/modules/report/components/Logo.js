
const Logo = ({image}) => {
    return (
        <img
            src={image}
            alt="Logo"
            className="img-thumbnail mt-1"
            width={100}
            height={100}
        />
    );
}

export default Logo;