import './select-all-style.scss';

function SelectAllRow(props) {
    return (  
        <div id="select_all_datarow">
            Select
            <div className="select-all">
                <div className="checkbox checkbox-success cursor-pointer ">
                    <input
                        id={"bulk-checkbox-select-all"}
                        type="checkbox"
                        name="bulk-checkbox-select-all"
                        className="m-1"
                        onChange={(e) => props.action(e)}
                        checked={props.checked?true:false}
                    />
                    <label htmlFor="bulk-checkbox-select-all"></label>
                </div>
            </div>
        </div>
    );
}

export default SelectAllRow;